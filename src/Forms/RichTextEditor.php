<?php

namespace Parallax\FilamentComments\Forms;

use Filament\Forms\Components\RichEditor;

class RichTextEditor extends RichEditor
{
    public array $customToolbarButtons = [];

    public function customButtons(array $buttons)
    {
        $options = collect($buttons[0]->options)->mapWithKeys(function ($value, $key) {
            return [str_replace('{{', '', str_replace('}}', '', $value)) => $key];
        })->toArray();

        $this->mergeTags($options);

        $this->customToolbarButtons = $buttons;

        return $this;
    }

    public function buttons(): array
    {
        return $this->customToolbarButtons;
    }
}
