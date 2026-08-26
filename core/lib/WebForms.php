<?php

// handles Webforms from the server side, retrieving forms from the database
// presenting to the user and handling the results


class formsClass {
    static function getForm($formname): null|webFormsClass {
        // retrieve form from database
        $dbForm = webFormsClass::sgetAllFilter('webforms', ['form_name' => $formname]);
        // echopre("Searching for form `$formname`");
        // echopre(print_r($dbForm, 1));
        if(!$dbForm)return null;

        return ($dbForm[0]);
    }
    static function getFormByGUID($guid): null|webFormsClass {
        // retrieve form from database
        $dbForm = webFormsClass::sgetAllFilter('webforms', ['guid' => $guid]);
        // echopre("Searching for form by guid `$guid`");
        // echopre(print_r($dbForm, 1));

        // Same "not found is a normal outcome, not a crash" fix as
        // getForm() above -- a guid with no matching row used to be an
        // uncaught "undefined array key 0" warning.
        if(!$dbForm)return null;

        return ($dbForm[0]);
    }


    // $view (new, optional, appended last -- same convention as
    // renderFormResults()'s own $view below) names one of this form's
    // yaml-declared form_view views; passed straight through to
    // generateHTMLForm(), which resolves it against that form's own
    // `default` when omitted.
    static function renderForm($formname, $view = null) {
        $form = self::getForm($formname);
        if($form) {
            $data = $form->getdata();
            $formArray = json_decode($data, true);

            // echopre( print_r($formArray, 1));
            $formArray['attributes']['action'] = rel_url("/webform/processform/".$form->getguid());
            return generateHTMLForm( $formArray, [], $view );
        } else {
            return "Form '$formname' not found!";
        }
    }

    static function getFormRenderArray($formname):array|null {
        $form = self::getForm( $formname );
        if($form) {
                $data = $form->getdata();
                $formArray = json_decode($data, true);
                $formArray['attributes']['action'] = rel_url("/webform/processform/".$form->getguid());

                return $formArray;

        } else {
            return null;
        }
    }

    static function renderFormArray($formArray, $view = null) {
        return generateHTMLForm( $formArray, [], $view );
    }

    // $formname string form name, $results array of results
    static function storeFormResults($form, $results) {

        $formArray = json_decode($form->getdata(), true);
        $formClass = $form->getform_class();

        // guid/cdate/cuser (the @guid/@cdate/@cuser yaml shorthand types
        // every generated entity class expects) are bookkeeping fields,
        // never actual form *inputs* -- a plain webform submission never
        // carries them. Without a guid specifically, insert() binds NULL
        // into a NOT NULL column and the query fails outright, which is
        // exactly what silently broke every generic (no custom
        // $_SESSION['handler']) webform submission before this fix, once
        // the call below was even reached at all. Only fill in what's
        // actually missing, so a caller that already set these (e.g. a
        // custom handler building its own $results) is unaffected.
        if(empty($results['guid']))$results['guid'] = guid();
        if(empty($results['cdate']))$results['cdate'] = getDBtime();
        if(empty($results['cuser'])) {
            global $kernel;
            if(isset($kernel))$results['cuser'] = $kernel->getUserName();
        }

        // echopre("store form results, formClass: $formClass");
        $classData = new $formClass( $results );
        // echopre(print_r($classData, 1));

        $classData->insert();
    }

    static function processform($params) {
        // These used to be unconditional echopre() calls -- meaning every
        // POST to this endpoint (this app's admin-only clinics/doctors
        // forms among them, now that they're actually wired up) dumped
        // the raw $_POST body (including the CSRF token) and, further
        // down, the entire $_SESSION array straight into the page's HTML
        // output. Left commented out, matching how every other debug
        // echopre() in this file is already handled, rather than deleted
        // outright, since they're genuinely useful when debugging a form
        // by hand.
        // echopre("Processing form: " . $params['guid']);

        // echopre("post:" . print_r($_POST, 1));

        // Opt-in only -- see csrfClass::$enforceWebforms's docblock
        // (core/lib/Csrf.php). An app that hasn't called
        // enableWebformProtection() reaches this unchanged.
        if(csrfClass::$enforceWebforms && !csrfClass::verifyRequest()) {
            return csrfClass::requireValid();
        }

        // Was `isset($_POST) && isset($_POST['submit'])` -- $_POST is
        // always set on this POST-only route (see webforms_post in
        // settings.info.yaml), and no button this framework generates has
        // ever been named literally "submit": ButtonElement names it
        // 'button_' . $label (e.g. button_Submit), so that second check
        // was never true for a real submission -- every generic (no
        // custom $_SESSION['handler']) webform silently saved nothing,
        // regardless of which button was clicked.
        if(!empty($_POST)) {
            $form = self::getFormByGUID($params['guid']);

            // echopre("Found form " . print_r($form, 1));
            // echopre(print_r($_POST, 1));

            // echopre(print_r($_SESSION, 1));

            global $kernel;
            $_POST['cuser'] = $kernel->getUserName();
            // Was `$_POST['pdob'] = getDBtime();` -- unconditionally
            // stomped any real 'pdob' (date of birth) field a form
            // actually submitted (e.g. operations.yaml's "Date of birth"
            // input) with the current timestamp, discarding whatever the
            // user typed. This function has no business special-casing
            // one particular app's field name at all.

            if(isset($_SESSION['handler'])) {
                $form_handler = $_SESSION['handler'];
                // echopre("form_handler: " . print_r($form_handler, 1));
                $guid = $form_handler['guid'];
                $handler = $form_handler['handler'];
                // echopre("form $guid handler $handler");


                
                if($handler) {
                    if(function_exists( $handler )) {
                        $result = $handler($params);
                        if($result) {
                            // handler returned succesfully
                            unset( $_SESSION['handler'] );
                            return $result;
                        } else {
                            return "ERROR: form handler `$handler` failed to execute";
                        }
                    } else {
                        return "ERROR: form handler `$handler` could not be found";
                    }
                } else {
                    return "ERROR: no form handler is defined";
                }
            }

            // No app-specific handler registered for this form -- generic
            // fallback: save directly via the form's own entity class
            // (storeFormResults() fills in guid/cdate/cuser, none of which
            // are real form inputs) and return to wherever the form was
            // submitted from, same "redirect to the referring page"
            // convention this app already uses elsewhere for a plain
            // save-and-go-back action.
            if($form) {
                self::storeFormResults($form, $_POST);
            }
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
            exit();
        }


        return "Form: {$params['guid']}";
    }

    static function viewform($params) {
        // echopre("Viewing form: " . $params['formname']);

        return self::renderFormResults($params['formname']);
    }


    // $view (new, optional, always appended LAST) names one of this form's
    // yaml-declared result-table views (document-root table_view: ->
    // <view-name>: [...], see generateHTMLFormArray() in
    // core/maker/functions.php) -- deliberately added as a 3rd parameter
    // rather than replacing/reordering the existing $filter_fields, so
    // every pre-existing 0/1/2-arg call site anywhere on the framework
    // keeps behaving identically. A form with no `table_view:`
    // block, or a $view that doesn't resolve to a real view, falls back to
    // exactly the pre-existing "every field that exists on the entity,
    // optionally narrowed by $filter_fields" behavior.
    static function renderFormResults($formname, $filter_fields = array(), $view = null) {
        $form = self::getForm($formname);
        // getForm() returns null when no `webforms` row exists for this
        // form name yet (e.g. `maker.php form:load` was never run for it)
        // -- every other call site here already treats that as a normal,
        // non-fatal outcome (renderForm() below returns a "not found"
        // string instead of crashing); this one used to call ->getdata()
        // on that null unconditionally, a hard fatal error rather than a
        // page that simply has nothing to show yet.
        if(!$form) return '';

        $formArray = json_decode($form->getdata(), true);
        // echopre(print_r($formArray, 1));

        $formClass = $form->getform_class();
        // echopre("Form class: " . $formClass);
        // echopre("Table name: " . $formArray['name']);
        $results = $formClass::sgetAllFilter($formArray['name']);
        // echopre("Results: " . print_r($results, 1));

        // A form with zero rows yet (e.g. a fresh install with no
        // clinics/doctors entered) has nothing to show -- was previously a
        // bare `return $output` with $output still an array at that point,
        // so every {{$var}} call site (a plain zetem echo, not a foreach)
        // printed PHP's "Array to string conversion" warning followed by
        // the literal word "Array".
        if(!count($results))return '';

        // Resolve which named view (if any) applies: an explicit $view
        // argument wins; otherwise fall back to the yaml's own `default:`
        // view, if it declared one; otherwise no view applies at all.
        // $columns, once resolved, is an ordered list of ['name' => ...,
        // 'label' => ...|null] controlling both which fields become
        // columns AND their left-to-right order -- generateHTMLFormTable()
        // derives its header row from whatever labels end up on
        // inputs_list[0], so a per-column 'label' override just needs to be
        // written onto that row's copy of the field definition below.
        // 'table_view' -- a document-root yaml key (promoted/renamed from
        // an earlier `form: -> table:` location; see
        // generateHTMLFormArray(), core/maker/functions.php).
        $resolvedView = $view ?? ($formArray['table_view']['default'] ?? null);
        $columns = null;
        if($resolvedView && !empty($formArray['table_view'][$resolvedView])) {
            $columns = $formArray['table_view'][$resolvedView];
        }

        if($columns === null) {
            // Legacy path -- no `table_view:` block on this form, or the
            // resolved view name doesn't exist: exactly today's behavior,
            // unchanged. Declared-input order, optionally narrowed by
            // $filter_fields.
            $columns = [];
            foreach($formArray['inputs'] as $input) {
                if(empty($filter_fields) || in_array($input['name'], array_values($filter_fields))) {
                    $columns[] = ['name' => $input['name'], 'label' => null];
                }
            }
        }

        // Indexed by name for O(1) lookup below, instead of re-scanning
        // $formArray['inputs'] per column per row.
        $inputsByName = array_column($formArray['inputs'], null, 'name');

        $formArray['inputs_list'] = [];
        // One entry per row, same index as inputs_list -- the row's own
        // guid/fields, needed to resolve per-row action buttons (below)
        // but otherwise irrelevant to the column-rendering loop above.
        // $entry->getguid() is only ever captured here, never exposed as a
        // regular column: `guid` is a bookkeeping field (see
        // storeFormResults()'s own comment on this file), never a real
        // form input, so it can never appear in $columns.
        $formArray['row_meta'] = [];

        foreach($results as $entry) {
            $input_row = [];
            $fields = $entry->getFields();
            // echopre("Fields: " . print_r($fields, 1));
            foreach($columns as $column) {
                $name = $column['name'];
                // A view may only ever surface a field that's also a real
                // form input (rendering a column needs that field's `type`
                // to pick the right *Element class) -- an unknown/stale
                // name in a view's field list, or one no longer present on
                // the loaded entity, is silently skipped, same as the
                // legacy path's own key_exists() check always was.
                if(!isset($inputsByName[$name]) || !key_exists($name, $fields))continue;

                $inputDef = $inputsByName[$name];
                $inputDef['default'] = $fields[$name];
                $inputDef['attributes']['disabled'] = "disabled";
                if(!empty($column['label']))$inputDef['label'] = $column['label'];

                $input_row[] = $inputDef;
            }
            $formArray['inputs_list'][] = $input_row;
            $formArray['row_meta'][] = ['guid' => $entry->getguid(), 'fields' => $fields];
        }

        // Optional per-row action buttons (Edit/Delete/custom -- see
        // zeusfw_normalize_table_row_buttons(), core/maker/functions.php)
        // and the header label for that synthetic column, both declared
        // once per table_view (not per named view -- the same row actions
        // make sense regardless of which columns happen to be showing).
        // Copied to top-level keys generateHTMLFormTable() reads directly,
        // same as inputs_list/row_meta above.
        $formArray['row_buttons'] = $formArray['table_view']['buttons'] ?? [];
        $formArray['actions_label'] = $formArray['table_view']['actions_label'] ?? 'Actions';

        // generateHTMLFormTable() derives the column headers itself from
        // the first row of inputs_list built above (FormElement.php), so
        // there's no separate hand-built header markup here any more --
        // that was the source of the broken comma-joined ", clinic_name,"
        // output shown with no <table>/<tr> wrapper at all.
        return generateHTMLFormTable($formArray);
    }
}


function setupFormActionHandler($form, $handler) {
    $guid = formsClass::getForm($form['name'])->getguid();
    $handler = [
        'handler' => $handler,
        'time' => time(),
        'guid' => $guid
    ];

    $_SESSION['handler'] = $handler;
    // echopre("setupFormActionHandler: " . print_r($handler, 1));
    // $handlerElement = new HiddenElement($form['name'], ['type' => 'hidden', 'name' => 'handler', 'value' => $handler] );
    // $formArray['handler'] = $handler;
}
