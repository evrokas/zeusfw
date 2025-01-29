<?php

// handles Webforms from the server side, retrieving forms from the database
// presenting to the user and handling the results


class formsClass {
    static function getForm($formname): null|webFormsClass {
        // retrieve form from database
        $dbForm = webFormsClass::sgetAllFilter('webforms', ['form_name' => $formname]);
        // echopre("Searching for form `$formname`");
        // echopre(print_r($dbForm, 1));

        return ($dbForm[0]);
    }
    static function getFormByGUID($guid): null|webFormsClass {
        // retrieve form from database
        $dbForm = webFormsClass::sgetAllFilter('webforms', ['guid' => $guid]);
        echopre("Searching for form by guid `$guid`");
        echopre(print_r($dbForm, 1));

        return ($dbForm[0]);
    }


    static function renderForm($formname) {
        $form = self::getForm($formname);
        if($form) {
            $data = $form->getdata();
            $formArray = json_decode($data, true);
            
            // echopre( print_r($formArray, 1));
            $formArray['attributes']['action'] = rel_url("/webform/processform/".$form->getguid());
            return generateHTMLForm( $formArray );
        } else {
            return ("Form '$formname' not found!");
        }
    }

    // $formname string form name, $results array of results
    static function storeFormResults($form, $results) {

        $formArray = json_decode($form->getdata(), true);
        $formClass = $form->getform_class();
        echopre("formClass: $formClass");
        $classData = new $formClass( $results );
        // echopre(print_r($classData, 1));
        
        $classData->setguid( guid() );
        $classData->setcdate( getDBtime() );
        
        global $kernel;
        $classData->setcuser( $kernel->getUserName() );

        $classData->insert();
    }

    static function processform($params) {
        echopre("Processing form: " . $params['guid']);


        if(isset($_POST) && isset($_POST['Submit'])) {
            $form = self::getFormByGUID($params['guid']);
            
            echopre("Found form " . print_r($form, 1));
            echopre(print_r($_POST, 1));
            
            self::storeFormResults($form, $_POST);

            // echopre("post:" . print_r($_POST, 1));
            // echopre("session:" . print_r($_SESSION, 1));
        }

        return "Form: {$params['guid']}";
    }

    static function viewform($params) {
        echopre("Viewing form: " . $params['formname']);

        return self::renderFormResults($params['formname']);
    }


    static function renderFormResults($formname) {
        $form = self::getForm($formname);

        $formArray = json_decode($form->getdata(), true);
        // echopre(print_r($formArray, 1));

        $formClass = $form->getform_class();
        // echopre("Form class: " . $formClass);
        // echopre("Table name: " . $formArray['name']);
        $results = $formClass::sgetAllFilter($formArray['name']);
        // echopre("Results: " . print_r($results, 1));

        unset($formArray['buttons']);

        $output = ['Clinics table results'];
        $html = [];
        $formArray['inputs_list'] = [];

        foreach($results as $entry) {
            $out = [];
            $input_row = [];
            $fields = $entry->getFields();
            // echopre("Fields: " . print_r($fields, 1));
            foreach($formArray['inputs'] as $key => $input) {
                // echopre("Input: " . $input['name']);
                if(key_exists($input['name'], $fields))
                    $out[] = "{{ " . $fields[ $input['name' ] ] . " }} ";
                $formArray['inputs'][$key]['default'] = $fields[ $input['name'] ];
                $formArray['inputs'][$key]['attributes']['disabled'] = "disabled";

                $input_row[] = $formArray['inputs'][$key];
                
            }
            $formArray['inputs_list'][] = $input_row;

            $output[] = implode(',', $out);
            
        }
        // echopre("input_lists: " . print_r($formArray, 1));
        $html[] = generateHTMLFormTable($formArray);


        $htmloutput = implode('', $html);
        $output[] = $htmloutput;

        $output = implode('<br>', $output);

        return $output;
    }
}
