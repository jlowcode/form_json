<?php

defined('_JEXEC') or die('Restricted access');

jimport('joomla.application.component.model');

require_once JPATH_SITE . '/components/com_fabrik/models/element.php';

class PlgFabrik_ElementForm_json extends PlgFabrik_Element {
    private $pluginLanguage;
    private $initialData;


    public function render($data, $repeatCounter = 0)
    {
        $input = $this->app->input;

        $displayData = new stdClass;
        $displayData->access = in_array('2', $this->user->getAuthorisedViewLevels());
        $displayData->view = $input->get('view');
        $displayData->attributes = $this->inputProperties($repeatCounter);

        $displayData->selectPlugin = $this->getHtmlSelectPlugin();

        $layout = $this->getLayout('details');

        return $layout->render($displayData);
    }

    private function getListFields() {
        $params = $this->getParams();
        $tableName = $params->get('form_json_list_fields_table');
        if (empty($tableName)) {
            return array();
        }

        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select('form_id')->from('#__fabrik_lists')->where("db_table_name = '{$tableName}'");
        $db->setQuery($query);
        $formId = $db->loadResult();

        $query = $db->getQuery(true);
        $query->select('group_id')->from('#__fabrik_formgroup')->where("form_id = '{$formId}'");
        $db->setQuery($query);
        $groupsId = $db->loadColumn();

        $elements = array();
        foreach ($groupsId as $groupId) {
            $query = $db->getQuery(true);
            $query->select('id, name')->from('#__fabrik_elements')->where("group_id = '{$groupId}'");
            $db->setQuery($query);
            $elements = array_merge($elements, $db->loadObjectList());
        }

        return $elements;
    }

    private function getHtmlSelectPlugin() {
        $plugins = JFolder::folders(JPATH_BASE . "/plugins/fabrik_form/");
        $exists = $this->verifyIfExists();

        $html = "<label for ='select_plugin'>Plugin</label>";

        $html .= "<select id='select_plugin' name='select_plugin'>";
        foreach($plugins as $item) {
            if (($exists !== false) && ($exists->plugins[0] === $item)) {
                $html .= "<option value='{$item}' selected>{$item}</option>";
            }
            else {
                $html .= "<option value='{$item}'>{$item}</option>";
            }
        }
        $html .= "</select>";

        $html .= "<button id='plugin_selected' type='button' class='btn btn-secondary button'>Selecionar</button><br><br>";

        $html .= "<div id='plugin_params'>";


        if ($exists !== false) {
            $html .= $this->getInitialHtml($exists);
        }

        $html .= "</div>";

        return $html;
    }

    private function verifyIfExists() {
        $formModel = $this->getFormModel();
        $table = $formModel->getTableName();
        $elementName = $this->element->name;
        $rowId = $formModel->data[$table . '___id'];

        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select($elementName)->from($table)->where("id = '{$rowId}'");
        $db->setQuery($query);
        $result = json_decode($db->loadResult());

        if (empty($result)) {
            return false;
        }

        return $result;
    }

    private function getInitialHtml($data) {
        $pluginName = $data->plugins[0];

        $language = JFactory::getLanguage();
        $tag = $language->getTag();
        $pluginLanguagePath = JPATH_BASE . "/plugins/fabrik_form/{$pluginName}/language/{$tag}/{$tag}.plg_fabrik_form_{$pluginName}.ini";
        $pluginFieldsXmlPath = JPATH_BASE . "/plugins/fabrik_form/{$pluginName}/forms/fields.xml";

        if ((!JFile::exists($pluginFieldsXmlPath)) || (!JFile::exists($pluginLanguagePath))) {
            return '';
        }

        $fieldsXml = simplexml_load_file($pluginFieldsXmlPath);
        $pluginLanguage = parse_ini_file($pluginLanguagePath);
        $this->pluginLanguage = $pluginLanguage;

        $html = '';

        $i = 0;
        $fieldsets = $fieldsXml->fields->fieldset;
        while ($fieldsets[$i] !== NULL) {
            $html .= "<div class='fabrikSubElementContainer'>";
            $html .= $this->createHtmlOfFields($fieldsets[$i]->field);
            $html .= "</div>";
            $i++;
        }

        return $html;
    }

    public function onCreateHtmlPluginFromXml() {
        $input     = $this->app->input;
        $this->setId($input->getInt('element_id'));
        $this->loadMeForAjax();

        $pluginName = $_POST["plugin_selected"];

        $language = JFactory::getLanguage();
        $tag = $language->getTag();
        $pluginLanguagePath = JPATH_BASE . "/plugins/fabrik_form/{$pluginName}/language/{$tag}/{$tag}.plg_fabrik_form_{$pluginName}.ini";
        $pluginFieldsXmlPath = JPATH_BASE . "/plugins/fabrik_form/{$pluginName}/forms/fields.xml";

        if ((!JFile::exists($pluginFieldsXmlPath)) || (!JFile::exists($pluginLanguagePath))) {
            echo json_encode('erro');
            return;
        }

        $fieldsXml = simplexml_load_file($pluginFieldsXmlPath);
        $pluginLanguage = parse_ini_file($pluginLanguagePath);
        $this->pluginLanguage = $pluginLanguage;

        $html = '';

        $i = 0;
        $fieldsets = $fieldsXml->fields->fieldset;
        while ($fieldsets[$i] !== NULL) {
            $html .= "<div class='fabrikSubElementContainer'>";
            $html .= $this->createHtmlOfFields($fieldsets[$i]->field);
            $html .= "</div>";
            $i++;
        }

        echo json_encode($html);
    }

    private function createHtmlOfFields($fields) {
        if (empty($fields)) {
            return '';
        }

        $html = '';

        $i = 0;
        while ($fields[$i] !== NULL) {
            $html .= "<div class='form-group'>";
            $html .= $this->createHtmlLabel($fields[$i]);
            $html .= $this->createHtmlFromType($fields[$i]);
            $html .= "</div>";

            $i++;
        }

        return $html;
    }

    private function createHtmlLabel($field) {
        $language = $this->pluginLanguage;

        $dom = new DOMDocument();

        $label = $dom->createElement('label');
        $label->setAttribute('for', (string) $field['name']);

        $labelText = $dom->createDocumentFragment();
        $labelText->appendXML($language[(string) $field['label']]);
        $label->appendChild($labelText);

        /*$html = "
                
                    <label for='" . (string) $field['name'] . "'
                           title 
                           data-content='" . JText::_((string) $field['description']) . "' 
                           data-original-title='" . JText::_((string) $field['label']) . "'
                    >
                        " . JText::_((string) $field['label']) . "
                    </label>
                
            ";*/

        return $dom->saveHTML($label);
    }

    private function createHtmlFromType($field) {
        $html = "";

        switch ((string) $field['type']) {
            case 'radio':
                $html .= $this->createHtmlRadioType($field);
                break;
            case 'listfields':
                $html .= $this->createHtmlListFieldsType($field);
                break;
            case 'text':
                $html .= $this->createHtmlTextType($field);
                break;
            case 'fabrikeditor':
                $html .= $this->createHtmlFabrikEditorType($field);
                break;
        }

        $html .= "";

        return $html;
    }

    private function createHtmlRadioType($field) {
        $language = $this->pluginLanguage;

        $dom = new DOMDocument();

        $div = $dom->createElement('div');

        $options = $field->option;
        $i = 0;
        while ($options[$i] !== NULL) {
            $input = $dom->createElement('input');
            $input->setAttribute('id', (string) $field['name'] . $i);
            $input->setAttribute('type', 'radio');
            $input->setAttribute('name', (string) $field['name']);
            $input->setAttribute('value', (string) $options[$i]['value']);
            $input->setAttribute('class', 'fabrikinput');
            if (($this->initialData !== NULL) && ($this->initialData[(string) $field['name']] === (string) $options[$i]['value'])) {
                $input->setAttribute('checked', 'checked');
            }
            else if ((string) $field['default'] === (string) $options[$i]['value']) {
                $input->setAttribute('checked', 'checked');
            }
            $label = $dom->createElement('label');
            $label->setAttribute('for', (string) $field['name'] . $i);

            $labelText = $dom->createDocumentFragment();
            $labelText->appendXML(JText::_((string) $options[$i]));
            $label->appendChild($labelText);

            $div->appendChild($input);
            $div->appendChild($label);

            $i++;
        }

        return $dom->saveHTML($div);
    }

    private function createHtmlListFieldsType($field) {
        $dom = new DOMDocument();

        $div = $dom->createElement('div');

        $select = $dom->createElement('select');
        $select->setAttribute('id', (string) $field['name']);
        $select->setAttribute('name', (string) $field['name']);

        $option = $dom->createElement('option');
        $option->setAttribute('value', '');
        $optionText = $dom->createDocumentFragment();
        $optionText->appendXML("Selecione");
        $option->appendChild($optionText);
        $select->appendChild($option);

        $fields = $this->getListFields();
        foreach ($fields as $field) {
            $option = $dom->createElement('option');
            $option->setAttribute('value', $field->id);
            if (($this->initialData !== NULL) && ($this->initialData[(string) $field['name']] === $field->id)) {
                $option->setAttribute('selected', '');
            }
            $optionText = $dom->createDocumentFragment();
            $optionText->appendXML($field->name);
            $option->appendChild($optionText);
            $select->appendChild($option);
        }

        $div->appendChild($select);

        return $dom->saveHTML($div);
    }

    private function createHtmlTextType($field) {
        $language = $this->pluginLanguage;
        $dom = new DOMDocument();

        $div = $dom->createElement('div');
        //$div->setAttribute('class', 'radio btn-radio btn-group');
        //$div->setAttribute('data-toggle', 'buttons');

        $input = $dom->createElement('input');
        $input->setAttribute('id', (string) $field['name']);
        $input->setAttribute('type', 'text');
        $input->setAttribute('name', (string) $field['name']);
        $input->setAttribute('class', 'fabrikinput');
        if (($this->initialData !== NULL) && (!empty($this->initialData[(string) $field['name']]))) {
            $input->setAttribute('value', $this->initialData[(string) $field['name']]);
        }

        $div->appendChild($input);
        
        return $dom->saveHTML($div);
    }

    private function createHtmlFabrikEditorType($field) {
        $dom = new DOMDocument();

        $div = $dom->createElement('div');

        $textarea = $dom->createElement('textarea');
        $textarea->setAttribute('id', (string) $field['name']);
        $textarea->setAttribute('name', (string) $field['name']);
        $textarea->setAttribute('rows', '5');
        //$input->setAttribute('class', 'fabrikinput');

        $div->appendChild($textarea);

        return $dom->saveHTML($div);
    }

    public function elementJavascript($repeatCounter)
    {
        $app = $this->app;

        $opts = $this->getElementJSOptions($repeatCounter);

        $opts->view = $app->input->get('view');
        $opts->element_id = $this->getId();

        $opts = json_encode($opts);

        $jsFiles = array();
        $jsFiles['Fabrik'] = 'media/com_fabrik/js/fabrik.js';
        $jsFiles['FbFormJson'] = 'plugins/fabrik_element/form_json/form_json.js';

        $script = "new FbFormJson($opts);";
        FabrikHelperHTML::script($jsFiles, $script);
    }

    private function getFieldsName($pluginName) {
        $pluginFieldsXmlPath = JPATH_BASE . "/plugins/fabrik_form/{$pluginName}/forms/fields.xml";

        if (!JFile::exists($pluginFieldsXmlPath)) {
            return array();
        }

        $fieldsXml = simplexml_load_file($pluginFieldsXmlPath);

        $fieldsName = array();
        $i = 0;
        $fieldsets = $fieldsXml->fields->fieldset;
        while ($fieldsets[$i] !== NULL) {
            $fields = $fieldsets[$i]->field;
            $j = 0;
            while($fields[$j] !== NULL) {
                $fieldsName[] = (string) $fields[$j]['name'];
                $j++;
            }
            $i++;
        }

        return $fieldsName;
    }

    private function updateTable() {
        $formModel = $this->getFormModel();
        $tableName = $formModel->getTableName();
        $elementName = $this->element->name;

        $db = JFactory::getDbo();
        $query = "ALTER TABLE {$tableName} MODIFY {$elementName} text";
        $db->setQuery($query);
        $db->execute();
    }

    public function onAfterProcess() {
        $this->updateTable();

        $formModel = $this->getFormModel();
        $formData = $formModel->formData;
        $tableName = $formModel->getTableName();

        $plugin = $formData['select_plugin'];
        $fieldsName = $this->getFieldsName($plugin);

        $json = new stdClass();
        foreach($fieldsName as $fieldName) {
            $json->$fieldName = $formData[$fieldName];
        }
        $json->plugin_state = array('1');
        $json->plugins = array($plugin);
        $json->plugin_locations = array('both');
        $json->plugin_events = array('both');
        $json->plugin_description = array();
        $json = json_encode($json);

        $rowId = $formData[$tableName . '___id'];
        $elementName = $this->element->name;

        $update = new stdClass();
        $update->id = $rowId;
        $update->$elementName = $json;
        JFactory::getDbo()->updateObject($tableName, $update, 'id');
    }
}