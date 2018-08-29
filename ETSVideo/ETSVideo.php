<?php
/*
 * École de technologie supérieure
 * Jean-Sébastien Gervais
 *
 * Basé sur le module vidéo HLS par défaut, mais redéfini le rendu html autour
 *
 */


namespace Xibo\Custom\ETSVideo;


use Respect\Validation\Validator as v;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\XiboException;
use Xibo\Factory\ModuleFactory;
use Xibo\Widget\ModuleWidget;

/**
 * Class Hls
 * @package Xibo\Custom
 */
class ETSVideo extends ModuleWidget
{

    public $codeSchemaVersion = 1;

    /**
     * Form for updating the module settings
     */
    public function settingsForm()
    {
        // Return the name of the TWIG file to render the settings form
        return 'ETSVideo-form-settings';
    }

    /**
     * Process any module settings
     * TODO
     */
    public function settings()
    {
        // Process any module settings you asked for.
        $this->module->settings['defaultMute'] = $this->getSanitizer()->getCheckbox('defaultMute');

        if ($this->getModule()->defaultDuration !== 0)
            throw new \InvalidArgumentException(__('The Video Module must have a default duration of 0 to detect the end of videos.'));

        // Return an array of the processed settings.
        return $this->module->settings;
    }



    /** @inheritdoc
    public function init()
    {
        // Initialise extra validation rules
        v::with('Xibo\\Validation\\Rules\\');
    }
     */

    /**
     * Install or Update this module
     * @param ModuleFactory $moduleFactory
     */
    public function installOrUpdate($moduleFactory)
    {
        if ($this->module == null) {
            // Install
            $module = $moduleFactory->createEmpty();
            $module->name = 'ETSVideo';
            $module->type = 'ETSVideo';
            $module->class = 'Xibo\Custom\ETSVideo\ETSVideo';
            $module->description = 'Module vidéo, gabarit ÉTS avec titre "Youtube"';
            $module->imageUri = 'forms/library.gif';
            $module->enabled = 1;
            $module->previewEnabled = 1;
            $module->assignable = 1;
            $module->regionSpecific = 0;
            $module->renderAs = 'html';
            $module->schemaVersion = $this->codeSchemaVersion;
            $module->defaultDuration = 60;
            $module->settings = [];
            $module->viewPath = '../custom/ETSVideo';
            $this->setModule($module);
            $this->installModule();
        }

        // Check we are all installed
        $this->installFiles();
    }

    /**
     * Install Files
     */
    public function installFiles()
    {
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/jquery-1.11.1.min.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-layout-scaler.js')->save();
        
        // Install files from a folder
        $folder = PROJECT_ROOT . '/custom/ETSVideo/resources';
        foreach ($this->mediaFactory->createModuleFileFromFolder($folder) as $media) { 
            $media->save();
        }
  
    }


    /**
     * Override previewAsClient
     * @param float $width
     * @param float $height
     * @param int $scaleOverride
     * @return string
     */
    public function previewAsClient($width, $height, $scaleOverride = 0)
    {
        return $this->previewIcon();
    }

    /**
     * Determine duration
     * @param $fileName
     * @return int
     */
    public function determineDuration($fileName = null)
    {
        // If we don't have a file name, then we use the default duration of 0 (end-detect)
        if ($fileName === null)
            return 0;

        $this->getLog()->debug('Determine Duration from %s', $fileName);
        $info = new \getID3();
        $file = $info->analyze($fileName);
        return intval($this->getSanitizer()->getDouble('playtime_seconds', 0, $file));
    }



    /**
     * Template for Layout Designer JavaScript  (adds javascript in module add/edit)
     * @return string
     */
    //public function layoutDesignerJavaScript()
    //{
    //  return 'my-module-javascript';
    //}
 
    public function add()
    {
        $this->setCommonOptions();
        $this->validate();

        // Save the widget
        $this->saveWidget();
    }

    /**
     * Edit Media
     */
    public function edit()
    {
        $this->setCommonOptions();
        $this->validate();

        // Save the widget
        $this->saveWidget();
    }
    /**
     * Set common options
     */
    private function setCommonOptions()
    {
        $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setOption('name', $this->getSanitizer()->getString('name'));
        $this->setOption('uri', urlencode($this->getSanitizer()->getString('uri')));
        $this->setOption('mute', $this->getSanitizer()->getCheckbox('mute'));

        // Only loop if the duration is > 0
        if ($this->getUseDuration() == 0 || $this->getDuration() == 0)
            $this->setOption('loop', 0);
        else
            $this->setOption('loop', $this->getSanitizer()->getCheckbox('loop'));

        // This causes some android devices to switch to a hardware accellerated web view
        $this->setOption('transparency', 0);
    }

    /**
     * Validate
     * @throws XiboException
     */
    private function validate()
    {
        if ($this->getUseDuration() == 1 && $this->getDuration() == 0)
            throw new InvalidArgumentException(__('Please enter a duration'), 'duration');

        if (!v::url()->notEmpty()->validate(urldecode($this->getOption('uri'))))
            throw new InvalidArgumentException(__('Please enter a link'), 'uri');
    }


    /**
     * Set default widget options
     */
    public function setDefaultWidgetOptions()
    {
        parent::setDefaultWidgetOptions();
        $this->setOption('mute', $this->getSetting('defaultMute', 0));
    }

    /**
     * @inheritdoc
     */
    public function isValid()
    {
        // Using the information you have in your module calculate whether it is valid or not.
        // 0 = Invalid
        // 1 = Valid
        // 2 = Unknown
        return 1;
    }

    /**
     * GetResource
     * Return the rendered resource to be used by the client (or a preview) for displaying this content.
     * @param integer $displayId If this comes from a real client, this will be the display id.
     * @return mixed
     */
    public function getResource($displayId = 0)
    {

        $this->getLog()->debug('getResource for ETSVIDEO called');

        // Ensure we have the necessary files linked up
        /* $media = $this->mediaFactory->createModuleFile(PROJECT_ROOT . '/modules/vendor/hls/hls.min.js');
        $media->save();
        $this->assignMedia($media->mediaId);
        $this->setOption('hlsId', $media->mediaId);

        $media = $this->mediaFactory->createModuleFile(PROJECT_ROOT . '/modules/vendor/hls/hls-1px-transparent.png');
        $media->save();
        $this->assignMedia($media->mediaId);
        $this->setOption('posterId', $media->mediaId);
        */

        $arrow  = $this->getResourceUrl('ets_arrow.png');
        $ytlogo = $this->getResourceUrl('youtube-logo.png');

        // Render and output HTML
        $this
            ->initialiseGetResource()
            ->appendViewPortWidth($this->region->width)
            ->appendJavaScriptFile('vendor/jquery-1.11.1.min.js')
            ->appendJavaScript('
                $(document).ready(function() {
                  
                });
            ')
            ->appendBody('
                <div>
                    <img src="' . $this->getResourceUrl('custom/ETSVideo/resources/ets_arrow.png') . '"/>
                    <img src="' . $this->getResourceUrl('custom/ETSVideo/resources/youtube-logo.png') . '"/>
                </div>
                <div>
                    <video id="video" poster="' . $this->getResourceUrl('vendor/hls/hls-1px-transparent.png') . '" ' . (($this->getOption('mute', 0) == 1) ? 'muted' : '') . '>
                    </video>
                </div>
            ')
            ->appendCss('
                video {
                    width: 100%; 
                    height: 100%;
                }
            ')
        ;

        return $this->finaliseGetResource();
    }

    /** @inheritdoc */
    public function getCacheDuration()
    {
        // We have a long cache interval because we don't depend on any external data.
        //return 86400 * 365;  
        //une heure
        return 3600;
    }
}