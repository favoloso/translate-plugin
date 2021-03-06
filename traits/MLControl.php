<?php namespace RainLab\Translate\Traits;

use Str;
use RainLab\Translate\Models\Locale;
use Backend\Classes\FormWidgetBase;
use Session;
use October\Rain\Html\Helper as HtmlHelper;

/**
 * Generic ML Control
 * Renders a multi-lingual control.
 *
 * @package rainlab\translate
 * @author Alexey Bobkov, Samuel Georges
 */
trait MLControl
{
    /**
     * @var boolean Determines whether translation services are available
     */
    public $isAvailable;

    /**
     * @var string Specifies a path to the views directory.
     */
    protected $parentViewPath;

    /**
     * @var RainLab\Translate\Models\Locale Object
     */
    protected $defaultLocale;

    /**
     * Initialize control
     * @return void
     */
    public function initLocale()
    {
        $this->defaultLocale = Locale::getDefault();
        $this->parentViewPath = $this->guessViewPathFrom(__TRAIT__, '/partials');
        $this->isAvailable = Locale::isAvailable();
    }

    /**
     * {@inheritDoc}
     */
    public function renderFallbackField()
    {
        return $this->makeParentPartial('fallback_field');
    }

    /**
     * Used by child classes to render in context of this view path.
     * @param string $partial The view to load.
     * @param array $params Parameter variables to pass to the view.
     * @return string The view contents.
     */
    public function makeParentPartial($partial, $params = [])
    {
        $oldViewPath = $this->viewPath;
        $this->viewPath = $this->parentViewPath;
        $result = $this->makePartial($partial, $params);
        $this->viewPath = $oldViewPath;

        return $result;
    }

    /**
     * Prepares the list data
     */
    public function prepareLocaleVars()
    {
        $this->vars['defaultLocale'] = $this->defaultLocale;
        $this->vars['locales'] = Locale::listAvailable();
        $this->vars['field'] = $this->makeRenderFormField();
    }

    /**
     * Loads assets specific to ML Controls
     */
    public function loadLocaleAssets()
    {
        $this->addJs('/plugins/rainlab/translate/assets/js/multilingual.js', 'RainLab.Translate');
        $this->addCss('/plugins/rainlab/translate/assets/css/multilingual.css', 'RainLab.Translate');
    }

    /**
     * Returns a translated value for a given locale.
     * @param  string $locale
     * @return string
     */
    public function getLocaleValue($locale)
    {
        $key = $this->valueFrom ?: $this->fieldName;

        /*
         * Get the translated values from the model
         */
        $studKey = Str::studly(implode(' ', HtmlHelper::nameToArray($key)));
        $mutateMethod = 'get'.$studKey.'AttributeTranslated';

        if ($this->model->methodExists($mutateMethod)) {
            return $this->model->$mutateMethod($locale);
        }
        elseif ($this->model->methodExists('getAttributeTranslated')) {
            return $this->model->noFallbackLocale()->getAttributeTranslated($key, $locale);
        }
        else {
            return $this->formField->value;
        }
    }

    /**
     * If translation is unavailable, render the original field type (text).
     */
    protected function makeRenderFormField()
    {
        if ($this->isAvailable) {
            return $this->formField;
        }

        $field = clone $this->formField;
        $field->type = $this->getFallbackType();

        return $field;
    }

    /**
     * {@inheritDoc}
     */
    public function getLocaleSaveValue($value)
    {
        $localeData = $this->getLocaleSaveData();
        $key = $this->valueFrom ?: $this->fieldName;

        /**
         * TODO Reimplement original feature
         */
        if ($this->model->methodExists('setAttributeTranslated')) {
            foreach ($localeData as $locale => $value) {
                $this->setTranslateAttribute($key, $value, $locale);
            }
        }

        return array_get($localeData, $this->defaultLocale->code, $value);
    }

    /*
     * Set the translated values to the model
     */
    public function setTranslateAttribute($key, $value, $locale)
    {
        $data = [['key' => $key, 'value' => $value, 'locale' => $locale]];
        $attributes = Session::get('RLTranslate.localeAttributes', []);
        $attributes = array_merge($attributes, $data);
        Session::put('RLTranslate.localeAttributes', $attributes);
    }

    /**
     * Returns an array of translated values for this field
     * @return array
     */
    public function getLocaleSaveData()
    {
        $values = [];
        $data = post('RLTranslate');

        if (!is_array($data)) {
            return $values;
        }

        $fieldName = implode('.', HtmlHelper::nameToArray($this->fieldName));

        foreach ($data as $locale => $_data) {
            $values[$locale] = array_get($_data, $fieldName);
        }

        return $values;
    }

    /**
     * Returns the fallback field type.
     * @return string
     */
    public function getFallbackType()
    {
        return defined('static::FALLBACK_TYPE') ? static::FALLBACK_TYPE : 'text';
    }
}
