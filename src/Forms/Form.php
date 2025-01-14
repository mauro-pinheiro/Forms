<?php

namespace Grafite\Forms\Forms;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\HtmlString;
use Illuminate\Routing\UrlGenerator;
use Grafite\Forms\Traits\HasErrorBag;
use Grafite\Forms\Builders\FieldBuilder;

class Form
{
    use HasErrorBag;

    /**
     * Laravel session
     *
     * @var Session
     */
    public $session;

    /**
     * The error bag
     *
     * @var mixed
     */
    public $errorBag;

    /**
     * If the form should be livewire based or not
     *
     * @var bool
     */
    public $withLivewire = false;

    /**
     * If the form should submit on keydown
     *
     * @var bool
     */
    public $livewireOnKeydown = false;

    /**
     * The model to be bound
     *
     * @var mixed
     */
    public $model;

    /**
     * The URL Generator
     *
     * @var \Illuminate\Contracts\Routing\UrlGenerator
     */
    public $url;

    /**
     * Html string for output
     *
     * @var string
     */
    protected $html;

    /**
     * Message for delete confirmation
     *
     * @var string
     */
    public $confirmMessage;

    /**
     * Method for delete confirmation
     *
     * @var string
     */
    public $confirmMethod;

    /**
     * A payload for the form
     *
     * @var array
     */
    public $payload;

    /**
     * An ID for the form
     *
     * @var string|null
     */
    public $formId = null;

    /**
     * Modal message
     *
     * @var string|null
     */
    public $message = null;

    /**
     * Content for a trigger button
     *
     * @var string|null
     */
    public $triggerContent = null;

    /**
     * Class for the trigger button
     *
     * @var string|null
     */
    public $triggerClass = null;

    /**
     * The reserved form open attributes.
     *
     * @var array
     */
    protected $reserved = [
        'method',
        'url',
        'route',
        'action',
        'files',
    ];

    /**
     * The form methods that should be spoofed, in uppercase.
     *
     * @var array
     */
    protected $spoofedMethods = [
        'DELETE',
        'PATCH',
        'PUT',
    ];

    /**
     * Create a new form builder instance.
     */
    public function __construct()
    {
        $this->url = app(UrlGenerator::class);
        $this->field = app(FieldBuilder::class);
        $this->session = session();
    }

    /**
     * Generate a button form based on method and route
     *
     * @param string $method
     * @param string $route
     * @param string $button
     * @return self
     */
    public function action($method, $route, $button = 'Send', $options = [], $asModal = false)
    {
        $this->html = $this->open([
            'route' => $route,
            'method' => $method,
            'class' => config('forms.form.inline-class', 'form d-inline'),
        ]);

        $options = array_merge([
            'class' => config('forms.buttons.submit', 'btn btn-primary'),
        ], $options);

        if (! empty($this->confirmMessage) && is_null($this->confirmMethod)) {
            $options = array_merge($options, [
                'onclick' => "return confirm('{$this->confirmMessage}')",
            ]);
        }

        if (! empty($this->confirmMessage) && ! is_null($this->confirmMethod)) {
            $options = array_merge($options, [
                'onclick' => "{$this->confirmMethod}(event, '{$this->confirmMessage}')",
            ]);
        }

        $options['type'] = 'submit';

        if (! empty($this->payload)) {
            foreach ($this->payload as $key => $value) {
                $this->html .= $this->field->makeInput('hidden', $key, $value);
            }
        }

        $this->html .= $this->field->button($button, $options);

        $this->html .= $this->close();

        if ($asModal) {
            $this->html = $this->asModal();
        }

        return $this;
    }

    /**
     * Get the Form ID
     *
     * @return string
     */
    public function getFormId()
    {
        if (! is_null($this->formId)) {
            return $this->formId;
        }

        return Str::random(10);
    }

    /**
     * Set the payload of an action form
     *
     * @param array $values
     * @return self
     */
    public function payload($values)
    {
        $this->payload = $values;

        return $this;
    }

    /**
     * Set the confirmation message for delete forms
     *
     * @param string $message
     * @param string $method
     *
     * @return self
     */
    public function confirm($message, $method = null)
    {
        $this->confirmMessage = $message;
        $this->confirmMethod = $method;

        return $this;
    }

    /**
     * Open up a new HTML form.
     *
     * cloned from LaravelCollective/html
     *
     * @param  array $options
     *
     * @return \Illuminate\Support\HtmlString
     */
    public function open($options)
    {
        $method = Arr::get($options, 'method', 'post');

        if (! $this->withLivewire) {
            $attributes['method'] = $this->getMethod($method);
            $attributes['action'] = $this->getAction($options);
        }

        $attributes['accept-charset'] = 'UTF-8';
        $append = '';

        if (! $this->withLivewire) {
            $append = $this->getAppendage($method);
        }

        if (isset($options['files']) && $options['files']) {
            $options['enctype'] = 'multipart/form-data';
        }

        $attributes = array_merge($attributes, Arr::except($options, $this->reserved));

        $attributes = $this->field->attributes($attributes);

        return $this->toHtmlString('<form' . $attributes . '>' . $append);
    }

    /**
     * Generate a close form tag
     *
     * cloned from LaravelCollective/html
     *
     * @return string
     */
    public function close()
    {
        return $this->toHtmlString('</form>');
    }

    /**
     * Generate a hidden field with the current CSRF token.
     *
     * cloned from LaravelCollective/html
     *
     * @return string
     */
    public function token()
    {
        return $this->field->makeInput('hidden', '_token', $this->session->token());
    }

    /**
     * Get the form appendage for the given method.
     *
     * cloned from LaravelCollective/html
     *
     * @param  string $method
     *
     * @return string
     */
    protected function getAppendage($method)
    {
        [$method, $appendage] = [strtoupper($method), ''];

        if (in_array($method, $this->spoofedMethods)) {
            $appendage .= $this->field->makeInput('hidden', '_method', $method);
        }

        if ($method !== 'GET') {
            $appendage .= $this->token();
        }

        return $appendage;
    }

    /**
     * Get the action for a "url" option.
     *
     * cloned from LaravelCollective/html
     *
     * @param  array|string $options
     *
     * @return string
     */
    protected function getUrlAction($options)
    {
        if (is_array($options)) {
            return $this->url->to($options[0], array_slice($options, 1));
        }

        return $this->url->to($options);
    }

    /**
     * Get the action for a "route" option.
     *
     * cloned from LaravelCollective/html
     *
     * @param  array|string $options
     *
     * @return string
     */
    protected function getRouteAction($options)
    {
        if (is_array($options)) {
            return $this->url->route($options[0], array_slice($options, 1));
        }

        return $this->url->route($options);
    }

    /**
     * Get the action for an "action" option.
     *
     * @param  array|string $options
     *
     * @return string
     */
    protected function getControllerAction($options)
    {
        if (is_array($options)) {
            return $this->url->action($options[0], array_slice($options, 1));
        }

        return $this->url->action($options);
    }

    /**
     * Get the form action from the options.
     *
     * cloned from LaravelCollective/html
     *
     * @param  array $options
     *
     * @return string
     */
    protected function getAction(array $options)
    {
        if (isset($options['url'])) {
            return $this->getUrlAction($options['url']);
        }

        if (isset($options['route'])) {
            return $this->getRouteAction($options['route']);
        } elseif (isset($options['action'])) {
            return $this->getControllerAction($options['action']);
        }

        return $this->url->current();
    }

    /**
     * Create a new model based form builder.
     *
     * cloned from LaravelCollective/html
     *
     * @param  mixed $model
     * @param  array $options
     *
     * @return \Illuminate\Support\HtmlString
     */
    public function model($model, array $options = [])
    {
        $this->model = $model;

        return $this->open($options);
    }

    /**
     * Set the model instance on the form builder.
     *
     * cloned from LaravelCollective/html
     *
     * @param  mixed $model
     *
     * @return void
     */
    public function setModel($model)
    {
        $this->model = $model;
    }

    /**
     * Get the current model instance on the form builder.
     *
     * cloned from LaravelCollective/html
     *
     * @return mixed $model
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Parse the form action method.
     *
     * cloned from LaravelCollective/html
     *
     * @param  string $method
     *
     * @return string
     */
    protected function getMethod($method)
    {
        $method = strtoupper($method);

        return $method !== 'GET' ? 'POST' : $method;
    }

    /**
     * Set the values for the modal trigger
     *
     * @param string $content
     * @param string $class
     * @param string $message
     *
     * @return self
     */
    public function setModal($content, $class, $message)
    {
        $this->triggerContent = $content;
        $this->triggerClass = $class;
        $this->message = $message;

        return $this;
    }

    /**
     * Create the form as a modal
     *
     * @return string
     */
    public function asModal($triggerContent = null, $triggerClass = null, $message = null)
    {
        $title = $this->modalTitle ?? 'Confirmation';
        $modalId = $this->getFormId() . '_Modal';
        $form = $this->html;
        $message = $this->message ?? $message;
        $triggerContent = $this->triggerContent ?? $triggerContent;
        $triggerClass = $this->triggerClass ?? $triggerClass;

        return <<<Modal
            <div id="${modalId}" class="modal fade" tabindex="-1" role="dialog">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">${title}</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            ${message}
                            ${form}
                        </div>
                    </div>
                </div>
            </div>
            <button
                onclick="$('#${modalId}').modal('show')"
                class="${triggerClass}"
            >
                ${triggerContent}
            </button>
Modal;
    }

    /**
     * Transform the string to an Html serializable object
     *
     * cloned from LaravelCollective/html
     *
     * @param $html
     *
     * @return \Illuminate\Support\HtmlString
     */
    protected function toHtmlString($html)
    {
        return new HtmlString($html);
    }

    /**
     * Output html as string
     *
     * @return string
     */
    public function __toString()
    {
        return $this->html;
    }
}
