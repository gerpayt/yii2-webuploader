<?php
/**
 * Created by PhpStorm.
 * User: cf
 * Date: 15-4-4
 * Time: 下午11:53
 */
namespace gerpayt\yii2_webuploader;

use Yii;
use yii\web\Controller;
use yii\web\Response;

use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use company\filters\CompanyUserChecked;
use company\filters\CompanyInfoChecked;


class WebUploaderController extends Controller
{


    public function beforeAction($action)
    {
        Yii::$app->request->enableCsrfValidation = false;
        return parent::beforeAction($action);
    }

    public function actionController()
    {
        $cid = Yii::$app->companyUser->cid;
        Yii::$app->response->format = Response::FORMAT_JSON;

        $http_header = Yii::$app->response->headers;

        // Make sure file is not cached (as it happens for example on iOS devices)
        $http_header->add('Expires', 'Mon, 26 Jul 1997 05:00:00 GMT');
        $http_header->add('Last-Modified', gmdate("D, d M Y H:i:s") . ' GMT');
        $http_header->add('Cache-Control', 'no-store, no-cache, must-revalidate');
        $http_header->add('Cache-Control', 'post-check=0, pre-check=0');
        $http_header->add('Pragma', 'no-cache');

        // Support CORS
        // header("Access-Control-Allow-Origin: *");
        // other CORS headers if any...
        if (Yii::$app->request->method == 'OPTIONS') {
            exit; // finish preflight CORS requests here
        }

        $debug = Yii::$app->request->get('debug');
        if ($debug) {
            $random = rand(0, intval($debug) );
            if ( $random === 0 ) {
                header("HTTP/1.0 500 Internal Server Error");
                exit;
            }
        }

        // 5 minutes execution time
        @set_time_limit(5 * 60);

        // Uncomment this one to fake upload time
        // usleep(5000);

        // Settings
        // $targetDir = ini_get("upload_tmp_dir") . DIRECTORY_SEPARATOR . "plupload";
        $targetDir = Yii::getAlias('@runtime') . '/upload_tmp';
        $folder = Yii::$app->request->post('folder');
        if(!array_key_exists($folder, Yii::$app->params['uploadFolders'])){
            return ["jsonrpc" => "2.0", "error" => ["code"=> 110, "message" => "Failed to locate upload folder."], "id" => "id"];
        }
        $dirName = call_user_func(Yii::$app->params['uploadFolders'][$folder]);
        $uploadDir = Yii::$app->params['UPLOAD_BASE_PATH'] . '/'.$dirName;

        $cleanupTargetDir = true; // Remove old files
        $maxFileAge = 5 * 3600; // Temp file age in seconds


        // Create target dir
        if (!file_exists($targetDir)) {
            @mkdir(dirname($targetDir));
            @mkdir($targetDir);
        }

        // Create target dir
        if (!file_exists($uploadDir)) {
            @mkdir(dirname($uploadDir));
            @mkdir($uploadDir);
        }

        // Get a file name
        if (Yii::$app->request->get('name')) {
            $fileName = Yii::$app->request->get('name');
        } elseif (!empty($_FILES)) {
            $fileName = $_FILES["file"]["name"];
        } else {
            $fileName = uniqid("file_");
        }

        $filePath = $targetDir . DIRECTORY_SEPARATOR . $fileName;
        $uploadPath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

        // Chunking might be enabled
        $chunk = isset($_REQUEST["chunk"]) ? intval($_REQUEST["chunk"]) : 0;
        $chunks = isset($_REQUEST["chunks"]) ? intval($_REQUEST["chunks"]) : 1;


        // Remove old temp files
        if ($cleanupTargetDir) {
            if (!is_dir($targetDir) || !$dir = opendir($targetDir)) {
                return ["jsonrpc" => "2.0", "error" => ["code" => 100, "message" => "Failed to open temp directory."], "id" => "id"];
            }

            while (($file = readdir($dir)) !== false) {
                $tmpfilePath = $targetDir . DIRECTORY_SEPARATOR . $file;

                // If temp file is current file proceed to the next
                if ($tmpfilePath == "{$filePath}_{$chunk}.part" || $tmpfilePath == "{$filePath}_{$chunk}.parttmp") {
                    continue;
                }

                // Remove temp file if it is older than the max age and is not the current file
                if (preg_match('/\.(part|parttmp)$/', $file) && (@filemtime($tmpfilePath) < time() - $maxFileAge)) {
                    @unlink($tmpfilePath);
                }
            }
            closedir($dir);
        }


        // Open temp file
        if (!$out = @fopen("{$filePath}_{$chunk}.parttmp", "wb")) {
            return ["jsonrpc" => "2.0", "error" => ["code"=> 102, "message" => "Failed to open output stream."], "id" => "id"];
        }

        if (!empty($_FILES)) {
            if ($_FILES["file"]["error"] || !is_uploaded_file($_FILES["file"]["tmp_name"])) {
                return ["jsonrpc" => "2.0", "error" => ["code" => 103, "message" => "Failed to move uploaded file."], "id" => "id"];
            }

            // Read binary input stream and append it to temp file
            if (!$in = @fopen($_FILES["file"]["tmp_name"], "rb")) {
                return ["jsonrpc" => "2.0", "error" => ["code" => 101, "message" => "Failed to open input stream."], "id" => "id"];
            }
        } else {
            if (!$in = @fopen("php://input", "rb")) {
                return ["jsonrpc" => "2.0", "error" => ["code" => 101, "message" => "Failed to open input stream."], "id" => "id"];
            }
        }

        while ($buff = fread($in, 4096)) {
            fwrite($out, $buff);
        }

        @fclose($out);
        @fclose($in);

        rename("{$filePath}_{$chunk}.parttmp", "{$filePath}_{$chunk}.part");

        $index = 0;
        $done = true;
        for( $index = 0; $index < $chunks; $index++ ) {
            if ( !file_exists("{$filePath}_{$index}.part") ) {
                $done = false;
                break;
            }
        }
        if ( $done ) {
            if (!$out = @fopen($uploadPath, "wb")) {
                return ["jsonrpc" => "2.0", "error" => ["code" => 102, "message" => "Failed to open output stream."], "id" => "id"];
            }

            if (flock($out, LOCK_EX) ) {
                for( $index = 0; $index < $chunks; $index++ ) {
                    if (!$in = @fopen("{$filePath}_{$index}.part", "rb")) {
                        break;
                    }

                    while ($buff = fread($in, 4096)) {
                        fwrite($out, $buff);
                    }

                    @fclose($in);
                    @unlink("{$filePath}_{$index}.part");
                }

                flock($out, LOCK_UN);
            }
            @fclose($out);
        }

        $fileFullName = $dirName.$fileName;

        // Return Success JSON-RPC response
        return ["jsonrpc" => "2.0", "result" => null, "id" => "id", 'filename' => $fileFullName];
    }

}
