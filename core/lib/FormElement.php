<?php

abstract class FormElement {
    protected $formname;
    protected $element;
    protected $template;
    protected $template_table;
    protected $template_suggestions;
    protected $template_suggestions_table;

    public function __construct($formname, $element) {
        $this->formname = $formname;
        $this->element = $element;
        [$this->template, $this->template_suggestions] = $this->getTemplate($formname);
        [$this->template_table, $this->template_suggestions_table] = $this->getTableTemplate($formname);

        // echo("element name: " . $this->element['name'] . "\n");
        // echo("template suggestions: " . print_r($this->template_suggestions, 1) . "\n");
        // echo("selected template: " . $this->template . "\n");
    }

    public function getTemplate($formname) {
        $tem_suggestions = [];
        Renderer::getTemplateSuggestions([
                'form' => $formname,
                'type' => $this->element['type'], 
                'name' => $this->element['name']
            ], 
            function($args, &$suggestions) {
                $suggestions[] = 'form_element';
                $suggestions[] = 'form_element_' . $args['type'];
                $suggestions[] = 'form_element_' . $args['name'];
                $suggestions[] = 'form_element_' . $args['form'] . '_' . $args['name'];
        }, $tem_suggestions);
        return [Renderer::getTemplate($tem_suggestions), $tem_suggestions];
    }

    public function getTableTemplate($formname) {
        $table_suggestions = [];
        Renderer::getTemplateSuggestions([
            'form' => $formname,
            'type' => $this->element['type'],
            'name' => $this->element['name']
        ],
            function($args, &$suggestions) {
                $suggestions[] = 'form_table_element';
                $suggestions[] = 'form_table_element_' . $args['type'];
                $suggestions[] = 'form_table_element_' . $args['name'];
                $suggestions[] = 'form_table_element_' . $args['form'] . '_' . $args['name'];
            }, $table_suggestions);

        return [Renderer::getTemplate($table_suggestions), $table_suggestions];
    }

    public function render($variables) {
        // echo("form element render function, variables: " . print_r($variables, 1) . "\n");
        // echo("template suggestions: " . print_r($this->template_suggestions, 1) . "\n");
        // echo("selected template: " . $this->template . "\n");

        return Renderer::render($this->template, $variables, [$this->template_suggestions, $this->template]);
    }
    
    public function renderTableItem($variables) {
        // echo("form element render function, variables: " . print_r($variables, 1) . "\n");
        // echo("template suggestions: " . print_r($this->template_suggestions_table, 1) . "\n");
        // echo("selected template: " . $this->template_table . "\n");

        return Renderer::render($this->template_table, $variables, [$this->template_suggestions_table, $this->template_table]);
    }

    abstract public function generateHTML();

    public function genetateHTMLforTable() {
        // echopre("generating HTML table info for " . print_r($this->element, 1));
        $variables = [
            'label' => $this->element['label'] ?? ucFirst($this->element['name']),
            'attributes' => $this->element['table-attributes'] ?? [],
            'value' => $this->element['default'],
        ];

        return ($this->renderTableItem($variables));
    }

    // public function generateHTMLforTableRow($elements) {
    //     $variables = [
    //         'row' => $elements
    //     ];

    //     return ($this->renderTableRow($variables));
    // }
}

function buildAttributes($attrs) {
    foreach($attrs as $key => $value) {
        echo("attribute '$key' == '$value'\n");
    }
}

class InputElement extends FormElement {
    public function generateHTML() {
        $attributes = $this->element['attributes'] ?? [];
        $attributes['name'] = $this->element['name'];
        $attributes['type'] = $this->element['type'];

        if (isset($this->element['default'])) {
            $attributes['value'] = $this->element['default'];
        }

        $variables = [
            'label' => $this->element['label'] ?? ucfirst($this->element['name']),
            'tag' => 'input', // The HTML tag for this element
            'attributes' => $attributes,
            // 'attributes' => buildAttributes($attributes),
            'options' => [], // No options for standard inputs
            'default' => '' // No content for input tags
        ];

        return $this->render($variables);
    }
}

class SelectElement extends FormElement {
    public function generateHTML() {
        $attributes = $this->element['attributes'] ?? [];
        $attributes['name'] = $this->element['name'];

        $options = [];
        foreach ($this->element['options'] as $option) {
            $options[] = [
                'value' => str_replace([' '], ['_'], $option),
                'label' => $option,
                'selected' => ($option == $this->element['default'])
            ];
        }

        $variables = [
            'label' => $this->element['label'] ?? ucfirst($this->element['name']),
            'tag' => 'select', // The HTML tag for this element
            'type' => 'select',
            'attributes' => $attributes,
            'options' => $options,
            'default' => '' // No content for select tags
        ];

        return $this->render($variables);
    }
}

class TextareaElement extends FormElement {
    public function generateHTML() {
        $attributes = $this->element['attributes'] ?? [];
        $attributes['name'] = $this->element['name'];

        $variables = [
            'label' => $this->element['label'] ?? ucfirst($this->element['name']),
            'tag' => 'textarea', // The HTML tag for this element
            'attributes' => $attributes,
            'options' => [], // No options for textarea tags
            'default' => $this->element['default'] ?? '' // Content inside the textarea
        ];

        return $this->render($variables);
    }
}

class BasicInputElement extends FormElement {
    public function generateHTML() {
        $attributes = $this->element['attributes'] ?? [];
        $attributes['name'] = $this->element['name'];
        $attributes['type'] = $this->element['type'];

        if (isset($this->element['default'])) {
            $attributes['value'] = $this->element['default'];
        }

        $variables = [
            'label' => $this->element['label'] ?? ucfirst($this->element['name']),
            'tag' => 'input', // HTML tag
            'attributes' => $attributes,
            'options' => [],
            'default' => ''
        ];

        return $this->render($variables);
    }
}

class CheckboxElement extends FormElement {
    public function generateHTML() {
        $attributes = $this->element['attributes'] ?? [];
        $attributes['name'] = $this->element['name'];
        $attributes['type'] = 'checkbox';

        if (!empty($this->element['default'])) {
            $attributes['checked'] = 'checked';
        }

        $variables = [
            'label' => $this->element['label'] ?? ucfirst($this->element['name']),
            'tag' => 'input',
            'attributes' => $attributes,
            'options' => [],
            'default' => ''
        ];

        return $this->render($variables);
    }
}

class RadioElement extends FormElement {
    public function generateHTML() {
        $radioGroup = [];
        foreach ($this->element['options'] as $option) {
            $attributes = $this->element['attributes'] ?? [];
            $attributes['name'] = $this->element['name'];
            $attributes['type'] = 'radio';
            $attributes['value'] = $option;

            if ($option === $this->element['default']) {
                $attributes['checked'] = 'checked';
            }

            $radioGroup[] = $this->render([
                'label' => $option,
                'tag' => 'input',
                'attributes' => $attributes,
                'options' => [],
                'default' => ''
            ]);
        }

        return implode("\n", $radioGroup);
    }
}


class HiddenElement extends FormElement {
    public function generateHTML() {
        $attributes = $this->element['attributes'] ?? [];
        $attributes['name'] = $this->element['name'];
        $attributes['type'] = 'hidden';

        if (isset($this->element['default'])) {
            $attributes['value'] = $this->element['default'];
        }

        $variables = [
            'label' => '', // No label for hidden inputs
            'tag' => 'input',
            'attributes' => $attributes,
            'options' => [],
            'default' => ''
        ];

        return $this->render($variables);
    }
}


class FileElement extends FormElement {
    public function generateHTML() {
        $attributes = $this->element['attributes'] ?? [];
        $attributes['name'] = $this->element['name'];
        $attributes['type'] = 'file';

        $variables = [
            'label' => $this->element['label'] ?? ucfirst($this->element['name']),
            'tag' => 'input',
            'attributes' => $attributes,
            'options' => [],
            'default' => ''
        ];

        return $this->render($variables);
    }
}


class ButtonElement extends FormElement {
    public function __construct($formname, $element)
    {
        $element['name'] = 'button_' . $element['label'];

        parent::__construct($formname, $element);
    }

    public function generateHTML() {
        $attributes = $this->element['attributes'] ?? [];
        // $attributes['name'] = $this->element['name'];
        $attributes['name'] = $this->element['label'];
        $attributes['type'] = $this->element['type']; // submit, reset, or button

        if (isset($this->element['value'])) {
            $attributes['value'] = $this->element['value'];
        } else $attributes['value'] = $this->element['label'];

        $variables = [
            // 'label' => $this->element['label'] ?? ucfirst($this->element['name']),
            'tag' => 'input',
            'attributes' => $attributes,
            'options' => [],
            'default' => ''
        ];

        return $this->render($variables);
    }
}


class RangeElement extends FormElement {
    public function generateHTML() {
        $attributes = $this->element['attributes'] ?? [];
        $attributes['name'] = $this->element['name'];
        $attributes['type'] = 'range';
        $attributes['min'] = $this->element['min'] ?? 0;
        $attributes['max'] = $this->element['max'] ?? 100;
        $attributes['step'] = $this->element['step'] ?? 1;

        if (isset($this->element['default'])) {
            $attributes['value'] = $this->element['default'];
        }

        $variables = [
            'label' => $this->element['label'] ?? ucfirst($this->element['name']),
            'tag' => 'input',
            'attributes' => $attributes,
            'options' => [],
            'default' => ''
        ];

        return $this->render($variables);
    }
}

/* 
function renderHTMLForm1($formArray) {
    // Extract form attributes
    $formAttributes = $formArray['attributes'] ?? [];
    $formInputs = $formArray['inputs'] ?? [];
    $formButtons = $formArray['buttons'] ?? [];

    // Initialize HTML output
    $htmlOutput = [];

    // Start the form tag
    $formAttributesString = '';
    foreach ($formAttributes as $key => $value) {
        $formAttributesString .= $key . '="' . htmlspecialchars($value) . '" ';
    }
    $htmlOutput[] = "<form $formAttributesString>";

    // Render inputs using respective FormElement classes
    foreach ($formInputs as $input) {
        $className = ucfirst($input['type']) . 'Element';

        if (class_exists($className)) {
            $element = new $className($input);
            $htmlOutput[] = $element->render();
        } else {
            $htmlOutput[] = "<!-- Unsupported input type: " . htmlspecialchars($input['type']) . " -->";
        }
    }

    // Render buttons using ButtonElement class
    foreach ($formButtons as $button) {
        $buttonElement = new ButtonElement($button);
        $htmlOutput[] = $buttonElement->render();
    }

    // Close the form tag
    $htmlOutput[] = "</form>";

    // Join all parts into a single HTML string
    return implode("\n", $htmlOutput);
}

 */
/* 
function generateHTMLForm1($formArray) {
    $formAttributes = $formArray['attributes'] ?? [];
    $inputs = $formArray['inputs'] ?? [];
    $buttons = $formArray['buttons'] ?? [];
    $elements = [];

    // Generate inputs
    foreach ($inputs as $input) {
        switch ($input['type']) {
            case 'text':
            case 'password':
                $elements[] = (new TextInput($input['attributes']))->render();
                break;
            case 'textarea':
                $elements[] = (new TextArea($input['attributes'], $input['default'] ?? ''))->render();
                break;
            // Add more input types as needed
        }
    }

    // Generate buttons
    foreach ($buttons as $button) {
        $elements[] = (new Button($button['attributes'], $button['label']))->render();
    }

    return [
        'attributes' => $formAttributes,
        'elements' => $elements,
    ];
}

 */

function generateHTMLForm($formArray) {
    $formName = $formArray['name'] ?? null;
    if(!$formName) {
        echo "Form is not named. Please set form name and try again.\n";
        exit(-1);
    }
    $formAttributes = $formArray['attributes'] ?? [];
    $inputs = $formArray['inputs'] ?? [];
    $buttons = $formArray['buttons'] ?? [];
    $elements = [];
    $controls = [];

    foreach($inputs as $input) {
        $className = ucfirst($input['type']) . 'Element';
        if(!class_exists($className))$className = 'BasicInputElement';

        if(class_exists($className)) {
            // echo(" Generating class for input {$input['name']}\n");
            $element = new $className( $formName, $input );
            $elements[] = $element->generateHTML();
            // $elements[] = $element->render();
        } else {
            $elements[] = "<!-- unsupported input type: " . htmlspecialchars($input['type']) . " -->";
        }
    }

    foreach($buttons as $button) {
        $buttonElement = new ButtonElement( $formName, $button );
        $controls[] = $buttonElement->generateHTML();
        // $elements[] = $buttonElement->render();
    }

    $template_suggestions = [];
    Renderer::getTemplateSuggestions(['type' => 'webform', 'name' => $formArray['name']], function($args, &$suggestions) {
        $suggestions[] = 'webform';
        $suggestions[] = 'webform--' . $args['name'];
    }, $template_suggestions);
    
    // print_r($template_suggestions);
    $template = Renderer::getTemplate($template_suggestions);

    $formAttributes['class'] = 'webform';

    return Renderer::render($template, [
                                'attributes' => $formAttributes, 
                                'elements' => $elements,
                                'controls' => $controls
                            ],
                        [$template_suggestions, $template]);
}

function generateHTMLTableRow($formname, $variables) {
    $row_suggestions = [];
    Renderer::getTemplateSuggestions([
        'form' => $formname,
    ],
    function($args, &$suggestions) {
        $suggestions[] = 'form_table_row';
        $suggestions[] = 'form_table_row_' . $args['form'];
    }, $row_suggestions);
    
    $row_template = Renderer::getTemplate($row_suggestions);
    Renderer::render($row_template, ['row' => $variables], [$row_suggestions, $row_template]);
}



function generateHTMLFormTable($formArray) {
    $formName = $formArray['name'] ?? null;
    if(!$formName) {
        echo "Form is not named. Please set form name and try again.\n";
        exit(-1);
    }
    $formAttributes = $formArray['attributes'] ?? [];
    $inputs_lists = $formArray['inputs_list'] ?? [];

    // we do not neeed buttons or controls in table form
    // $buttons = $formArray['buttons'] ?? [];
    // $controls = [];

    $elements = [];
    
    foreach($inputs_lists as $inputs) {
        $row_elements = [];
        foreach($inputs as $input) {
            // echopre("processing form input: " . print_r($input,1));
            $className = ucfirst($input['type']) . 'Element';
            if(!class_exists($className))$className = 'BasicInputElement';

            if(class_exists($className)) {
                // echo(" Generating class for input {$input['name']}\n");
                $element = new $className( $formName, $input );
                $row_elements[] = $element->genetateHTMLforTable();
                // $elements[] = $element->render();
            } else {
                $row_elements[] = "<!-- unsupported input type: " . htmlspecialchars($input['type']) . " -->";
            }
        }

        // $elements[] = generateHTMLTableRow($formName, $row_elements);
        // echopre("row: " . print_r($row_elements, 1));
        $elements[] = implode(' ', $row_elements);
    }
    // foreach($buttons as $button) {
    //     $buttonElement = new ButtonElement( $formName, $button );
    //     $controls[] = $buttonElement->generateHTML();
    //     // $elements[] = $buttonElement->render();
    // }

    $template_suggestions = [];
    Renderer::getTemplateSuggestions(['type' => 'webform', 'name' => $formArray['name']], function($args, &$suggestions) {
        $suggestions[] = 'webform_table';
        $suggestions[] = 'webform_table__' . $args['name'];
    }, $template_suggestions);
    
    // print_r($template_suggestions);
    $template = Renderer::getTemplate($template_suggestions);

    $formAttributes['class'] = 'webform';

    return Renderer::render($template, [
                                'attributes' => $formAttributes, 
                                'elements' => $elements,
                                // 'controls' => $controls
                            ],
                        [$template_suggestions, $template]);
}
