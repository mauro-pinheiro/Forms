<?php

namespace Grafite\Forms\Components;

use Illuminate\View\Component;

class FormModal extends Component
{
    public $payload;

    public $triggerContent;

    public $triggerClass;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct(
        $route,
        $method = 'post',
        $content = '',
        $message = '',
        $payload = [],
        $options = [],
        $triggerContent = 'Trigger',
        $triggerClass = null
    ) {
        $this->route = $route;
        $this->method = $method;
        $this->message = $message;
        $this->content = $content;
        $this->payload = $payload;
        $this->options = $options;
        $this->triggerContent = $triggerContent;
        $this->triggerClass = $triggerClass;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\View\View|string
     */
    public function render()
    {
        $form = form();

        return (string) $form->payload($this->payload)
            ->setModal($this->triggerContent, $this->triggerClass, $this->message)
            ->action($this->method, $this->route, $this->content, $this->options, true);
    }
}
