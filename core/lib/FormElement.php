<?php

abstract class FormElement {
    protected $formname;
    protected $element;
    protected $template;
    protected $template_table;
    protected $template_suggestions;
    protected $template_suggestions_table;
    protected $default_value;

    public function __construct($formname, $element, $default = null) {
        $this->formname = $formname;
        $this->element = $element;
        [$this->template, $this->template_suggestions] = $this->getTemplate($formname);
        [$this->template_table, $this->template_suggestions_table] = $this->getTableTemplate($formname);
        $this->default_value = $default;

        // echo("element name: " . $this->element['name'] . "\n");
        // echo("template suggestions: " . print_r($this->template_suggestions, 1) . "\n");
        // echo("selected template: " . $this->template . "\n");
    }

    public function getTemplate($formname) {
        $tem_suggestions = [];
        Renderer::getTemplateSuggestions([
                'form' => $formname,
                'type' => $this->element['type'] ?? null, 
                'button_type' => $this->element['button_type']??null,
                'name' => $this->element['name']
            ], 
            function($args, &$suggestions) {
                $suggestions[] = 'form_element';
                $suggestions[] = 'form_element_' . $args['type'];
                if($args['button_type'])
                    $suggestions[] = 'form_element_' . $args['type'] . '--' . $args['button_type'];
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
            'value' => $this->default_value ?? $this->element['default'],
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
        } else $attributes['value'] = $this->default_value ?? null;

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
function sanitize(string $str): string {
    return str_replace([' '], ['_'], $str);
}


function formBuildWhereClause(array $filters): string {
    $conditions = [];
    
    foreach ($filters as $key => $value) {
        // Handle logical operators (AND/OR/NOT)
        if (in_array(strtolower($key), ['and', 'or', 'not'])) {
            $operator = strtoupper($key);
            $subConditions = [];
            
            foreach ($value as $subFilter) {
                $subConditions[] = formBuildWhereClause($subFilter);
            }
            
            if ($operator === 'NOT') {
                $conditions[] = "NOT (" . implode(' AND ', $subConditions) . ")";
            } else {
                $conditions[] = "(" . implode(" $operator ", $subConditions) . ")";
            }
        }
        // Handle field conditions
        else {
            // Check for operator syntax (e.g., {operator: ">", value: 10})
            if (is_array($value) && isset($value['operator']) && isset($value['value'])) {
                $operator = strtoupper($value['operator']);
                $val = $value['value'];
            } else {
                $operator = '=';
                $val = $value;
            }

            // Format value based on type
            if (is_array($val)) {
                $formattedVal = "(" . implode(', ', array_map(fn($v) => is_string($v) ? "'$v'" : $v, $val)) . ")";
            } else {
                $formattedVal = is_string($val) ? "'$val'" : $val;
            }

            $conditions[] = "$key $operator $formattedVal";
        }
    }
    
    return implode(' AND ', $conditions);
}

/* // Example usage
$yaml = <<<YAML
query:
  operation: select
  table: doctors
  fields:
    - guid
    - doctor_name
  filters:
    and:
      - or:
          - doctor_specialty: "Cardiology"
          - doctor_specialty: "Neurology"
      - years_experience: { operator: ">=", value: 10 }
      - hospital_id: { operator: "IN", value: [123, 456] }

// Build WHERE clause
$where = !empty($query['filters']) ? buildWhereClause($query['filters']) : '';
 */
class SelectElement extends FormElement {
    public function generateHTML() {
        $attributes = $this->element['attributes'] ?? [];
        $attributes['name'] = $this->element['name'];

        $options = [];
        if(!empty($this->element['options'])) {
            foreach ($this->element['options'] as $option) {
                $options[] = [
                    'value' => str_replace([' '], ['_'], $option),
                    'label' => $option,
                    'selected' => ($option == $this->element['default'])
                ];
            }
        } else
        if(!empty($this->element['static_options'])) {
            // echopre(print_r($this->element, 1));

            $tableNameClass = $this->element['static_options']['params']['tableclass'] ?? null;
            $values = $this->element['static_options']['params']['values'] ?? null;
            $label = $this->element['static_options']['params']['label'] ?? null;
            $filters = $this->element['static_options']['params']['filter'] ?? null;

            if(!class_exists($tableNameClass))return "Table $tableNameClass does not exist.";
            if(!$label)return "Empty label field";
            if(!is_array(($label)))
                $label = [$label];

            $tableClass = new ($tableNameClass)();

            if($filters) {
                $filterClause = formBuildWhereClause($filters);
                // echopre("filters: " . print_r($filterClause, 1));
            }

            $opts = $tableClass::sgetAll( $filterClause ?? null );
            // $optsFields = ($tableClass)->getFields();
            $value = $this->element['static_options']['params']['value'] ?? 'select_value';

            // echopre("value: " . print_r($value, 1));
            if(!empty($opts)) {
                foreach($opts as $option) {
                    // echopre("options: " . print_r($option, 1));
                    $topt = [
                        // 'value' => str_replace([' '], ['_'], implode('__', $values)),
                        // 'label' => str_replace([' '], ['_'], implode('__', $values)),
                        'label' => sanitize( $option->getFieldValue('get'.$value) ),
                        'selected' => false
                    ];
                    
                    $tlabel = [];
                    foreach($label as $label_option) {
                        $toptlabel = $option->getFieldValue('get'.$label_option);
                        $topt = array_merge($topt, [$label_option => $toptlabel ] );
                        $tlabel[] = $toptlabel;

                        // $topt[ $option->getFieldValue'label'] = str_replace([' '], ['_'], $option->getFieldValue('get'.$val));
                    }
                    $topt['value'] = sanitize( $option->getFieldValue('get'.$value) );
                    $topt['label'] = implode(' ', $tlabel);

                    $options[] = $topt;
                    // echopre("final options:" . print_r($options, 1));
                }
                
            }
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
            'default' => $this->default_value ?? $this->element['default'] ?? '' // Content inside the textarea
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
        } else $attributes['value'] = $this->default_value ?? null;

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

    // FormElement::getTemplate()'s suggestion list falls all the way back
    // to the bare 'form_element' template when nothing more specific
    // matches -- correct for a text/select/textarea input, but that
    // template only ever closes a <select> or <textarea> tag, leaving any
    // other $tag (a <button>) unclosed with no visible label text. Only a
    // button whose own yaml `type:` happens to be the literal string
    // "button" ever reached 'form_element_button.zetem' by suggestion-name
    // coincidence; type: submit/reset fell through to the broken generic
    // one instead. Every button, regardless of its submit/reset/button
    // type, needs the same real button markup -- so this override swaps
    // the generic 'form_element' fallback for 'form_element_button',
    // keeping every more-specific suggestion (per name, per form+name,
    // per type--button_type) so an app can still override one exact
    // button if it wants to.
    public function getTemplate($formname) {
        $tem_suggestions = [];
        Renderer::getTemplateSuggestions([
                'form' => $formname,
                'type' => $this->element['type'] ?? null,
                'button_type' => $this->element['button_type'] ?? null,
                'name' => $this->element['name']
            ],
            function($args, &$suggestions) {
                $suggestions[] = 'form_element_button';
                $suggestions[] = 'form_element_' . $args['type'];
                if($args['button_type'])
                    $suggestions[] = 'form_element_' . $args['type'] . '--' . $args['button_type'];
                $suggestions[] = 'form_element_' . $args['name'];
                $suggestions[] = 'form_element_' . $args['form'] . '_' . $args['name'];
        }, $tem_suggestions);
        return [Renderer::getTemplate($tem_suggestions), $tem_suggestions];
    }

    public function generateHTML() {
        $attributes = $this->element['attributes'] ?? [];
        $attributes['name'] = $this->element['name'];
        // $attributes['name'] = $this->element['label'];
        // $attributes['type'] = $this->element['type']; // submit, reset, or button
        $attributes['type'] = $this->element['button_type']; // submit, reset, or button

        if (isset($this->element['value'])) {
            $attributes['value'] = $this->element['value'];
        } else $attributes['value'] = $this->element['label'];

        $variables = [
            'label' => $this->element['label'] ?? ucfirst($this->element['name']),
            // 'tag' => 'input',
            'type' => $this->element['button_type'],
            'value' => $this->element['value'],
            'tag' => 'button',
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

function generateHTMLForm($formArray, $default_values = array() ) {
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
    $formelements = [];



    foreach($inputs as $input) {
        $className = ucfirst($input['type']) . 'Element';
        if(!class_exists($className))$className = 'BasicInputElement';

        if(class_exists($className)) {
            // echo(" Generating class for input {$input['name']}\n");
            $element = new $className( $formName, $input, $default_values[ $input['name'] ] ?? null);
            $elements[] = $element->generateHTML();
            $formelements[ $input['name' ] ] = $element;

            // $elements[] = $element->render();
        } else {
            $elements[] = "<!-- unsupported input type: " . htmlspecialchars($input['type']) . " -->";
        }
    }

    // echopre("form elements: " . print_r($formelements, 1));

    foreach($buttons as $button) {
        // echopre("button: " . print_r($button, 1));
        $buttonElement = new ButtonElement( $formName, $button );
        $controls[] = $buttonElement->generateHTML();
        // $elements[] = $buttonElement->render();
    }

    // Purely additive -- see csrfClass::$enforceWebforms's docblock
    // (core/lib/Csrf.php). Every DB-defined webform gets a token field
    // regardless of whether the owning app has opted into enforcement;
    // an app that hasn't is completely unaffected since nothing reads
    // this input unless processform() is told to check it.
    if(function_exists('csrf_field')) {
        $elements[] = csrf_field();
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
                                'formelements' => $formelements,
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

    // Headers come from the first row's own field definitions (already
    // filtered down to whichever fields renderFormResults() actually
    // selected -- via $filter_fields there, or simply "exists on this
    // entity" otherwise) rather than re-deriving that filter here, so
    // there's exactly one place that decides which columns appear.
    $headers = [];
    if(!empty($inputs_lists[0])) {
        foreach($inputs_lists[0] as $input) {
            $headers[] = $input['label'] ?? ucfirst($input['name']);
        }
    }

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

    //   ** DO NOT render buttons **
    //  foreach($buttons as $button) {
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
                                'headers' => $headers,
                                // 'controls' => $controls
                            ],
                        [$template_suggestions, $template]);
}

function generateFormButton($button_type, $label, $value) {
    $result = ['type' => 'button',
            'button_type' => $button_type,
            'label' => $label,
            'value' => $value ];
 
    return $result;
}

function formArrayAddButton($formArray, $button) {
    // echopre("adding button: " . print_r($button, 1));
    // echopre("form array: " . print_r($formArray, 1));
    if(!isset($formArray['buttons']))
        $formArray['buttons'] = [];

    $formArray['buttons'][] = $button;

    return $formArray;
}

