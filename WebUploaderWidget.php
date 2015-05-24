<?php

namespace gerpayt\yii2_webuploader;

use yii\base\InvalidParamException;
use yii\helpers\BaseHtml;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\widgets\InputWidget;

class WebUploaderWidget extends InputWidget
{
    public $thumb = null;
    public $thumbs = [];
    public $url_prefix;

    public $parts = [];
    public $widgetTemplate = "{input}\n{picker}\n{cropper}\n{thumbnail}\n{upload}";

    public function init()
    {
        parent::init();

        if ($this->thumb == null and $this->thumbs == []) {
            if ($this->thumbs == []) {
                $this->thumbs[''] = $this->thumb;
            }
        } elseif ($this->thumb == null xor $this->thumbs == []) {
            if ($this->thumbs == []) {
                $this->thumbs[''] = $this->thumb;
            }
        } else {
            throw new InvalidParamException('Wrong params of thumb(s).');
        }

        $options = $this->options;

        if (isset($options['id'])) {
            $this->id = $options['id'];
        } else {
            $this->id = 'webuploader';
        }

    }
    public function run()
    {
        $this->input();
        $this->picker();
        $this->cropper();
        $this->thumbnail();
        $this->upload();

        $content = strtr($this->widgetTemplate, $this->parts);

        $view = $this->getView();
        $assets = WebUploaderAsset::register($view);

        echo Html::beginTag('div', ['class' => 'webuploader-cropper',
            'data-server-url' => Url::to(['webuploader/controller']),
            'data-swf-url' => $assets->baseUrl.'/Uploader.swf']);
        echo $content;
        echo Html::endTag('div');
    }

    public function input()
    {
        $this->parts['{input}'] = Html::activeHiddenInput($this->model, $this->attribute, $this->options);
        return $this;
    }
    public function picker()
    {
        $this->parts['{picker}'] = Html::tag('span', '选择图片', ['id' => $this->id.'-picker']);
        return $this;
    }
    public function cropper()
    {
        $this->parts['{cropper}'] = Html::beginTag('div', ['class' => $this->id.'-cropper-wrapper', 'style' => 'display:none;']);
        $this->parts['{cropper}'] .= Html::beginTag('div',['class' => 'img-container', 'style' => 'max-height:300px;']);
        $this->parts['{cropper}'] .= Html::img('');
        $this->parts['{cropper}'] .= Html::endTag('div');
        $this->parts['{cropper}'] .= Html::endTag('div');
        return $this;

    }
    public function thumbnail()
    {
        $this->parts['{thumbnail}'] = Html::beginTag('div', ['class' => 'img-thumbnail']);
        $max_width = 0;
        $max_height = 0;
        foreach ($this->thumbs as $name=>$config) {

            if ($name === '') {
                $value_name = 'value';
                $attribute_name = $this->attribute;
            } else {
                $value_name = 'value_'.$name;
                $attribute_name = $this->attribute.'_'.$name;
            }
            if (isset($options[$value_name])) {
                $value = $options[$value_name];
            } elseif ($this->hasModel()) {
                $value = BaseHtml::getAttributeValue($this->model, $attribute_name);
            } else {
                $value = $this->__get($value_name);
            }

            if ($config['height'] > $max_height)
                $max_height = $config['height'];

            if ($config['width'] > $max_width)
                $max_width = $config['width'];

            $this->parts['{thumbnail}'] .= Html::beginTag('div', ['class' => $this->id.'-img-preview', 'style' =>
                'height:'.$config['height'].'px; width:'.$config['width'].'px; margin-top: 1px;']);
            $this->parts['{thumbnail}'] .= '<img src="'.$this->url_prefix.$value.'" alt="" />';
            $this->parts['{thumbnail}'] .= Html::endTag('div');
        }
        $this->parts['{thumbnail}'] .= Html::endTag('div');
        return $this;

    }
    public function upload()
    {
        $this->parts['{upload}'] = Html::tag('button', '上传图片', ['id' => $this->id.'-upload', 'class'=>'btn btn-primary', 'style'=>'display:none;']);
        return $this;
    }
}
