<?php

use midcom\datamanager\template\base;
use Symfony\Component\Form\FormView;

class datamanager_form extends base
{
    public function form_start(FormView $view, array $data)
    {
        return '{"' . $data['name'] . '": {';
    }

    public function form_end(FormView $view, array $data)
    {
        return '}}';
    }

    public function form_errors(FormView $view, array $data)
    {
        return '';
    }

    public function form_widget_compound(FormView $view, array $data)
    {
        $string = $this->renderer->block($view, 'form_rows');
        $string .= $this->renderer->rest($view);
        if ($view->parent) {
            $string = '{' . $string . '}';
        }
        return $string;
    }

    public function form_widget_simple(FormView $view, array $data)
    {
        return '"' . $data['value'] . '"';
    }

    public function form_rows(FormView $view, array $data)
    {
        $string = '';
        foreach ($view as $child) {
            $string .= $this->renderer->row($child);
        }
        return $string;
    }

    public function form_row(FormView $view, array $data)
    {
        return '"' . $data['name'] . '": ' . $this->renderer->widget($view) . ',';
    }

    public function button_row(FormView $view, array $data)
    {
        return '';
    }

    public function password_row(FormView $view, array $data)
    {
        return '';
    }

    public function toolbar_row(FormView $view, array $data)
    {
        return '';
    }

    public function choice_widget_collapsed(FormView $view, array $data)
    {
        if (is_array($data['value'])) {
            $ret = '[';
            foreach ($data['value'] as $val) {
                $ret .= '"' . $val . '",';
            }
            return $ret . ']';
        }
        return $this->form_widget_simple($view, $data);
    }

    public function choice_widget_expanded(FormView $view, array $data)
    {
        foreach ($view->children as $child) {
            if ($child->vars['checked']) {
                return '"' . $data['name'] . '": "' . $child->vars['value'] . '",';
            }
        }
        return '""';
    }

    public function image_widget(FormView $view, array $data)
    {
        return '[]';
    }

    public function radiocheckselect_row(FormView $view, array $data)
    {
        foreach ($view->children as $child) {
            if ($child->vars['checked']) {
                return '"' . $data['name'] . '": "' . $child->vars['value'] . '",';
            }
        }
        return '';
    }

    public function org_openpsa_user_widget_password_widget(FormView $view, array $data)
    {
        $string = '{"password": ' . $this->renderer->widget($view['password']) . ',';
        $string .= $this->renderer->row($view['switch']) . '}';
        return str_replace(',}', '}', $string);
    }

    public function autocomplete_widget(FormView $view, array $data)
    {
        return '{"selection": ' . $this->renderer->widget($view['selection']) . '}';
    }

    public function other_widget(FormView $view, array $data)
    {
        $string = '{"select": ' . $this->renderer->widget($view['select']) . ', ';
        return $string . '"other": ' . $this->renderer->widget($view['other']) . '}';
    }

    public function jsdate_widget(FormView $view, array $data)
    {
        $string = '{"date": ' . $this->renderer->widget($view['date']) . ',';

        if (isset($view['time'])) {
            $string .= '"time": ' . $this->renderer->widget($view['time']);
        }
        return $string . '}';
    }
}
