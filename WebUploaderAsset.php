<?php

namespace gerpayt\yii2_webuploader;

use yii\web\AssetBundle;
use yii\web\JqueryAsset;

class WebUploaderAsset extends AssetBundle
{
    public $sourcePath = '@vendor/gerpayt/yii2-webuploader/src/dist';

    public $depends = [
        'yii\bootstrap\BootstrapPluginAsset',
        'yii\bootstrap\BootstrapPluginAsset'
    ];

    public function init()
    {
        $this->css[] = 'cropper.css';
        $this->js[] = YII_DEBUG ? 'webuploader.js' : 'webuploader.min.js';
        $this->js[] = 'cropper.js';
        $this->js[] = 'bootstrap-filestyle.js';
    }

}
