<?php

namespace Lightning\Tools;

use Exception;
use Lightning\Tools\Cache\FileCache;
use Lightning\View\Field\BasicHTML;
use Lightning\Model\Page;

class CSVImport extends Page {
    protected $fields;
    protected $key;
    protected $table;
    protected $handlers = [];
    protected $values = [];

    /**
     * @var CSVIterator
     */
    protected $csv;
    public $importAction = 'import';
    public $importAlignAction = 'import-align';

    /**
     * @var FileCache
     */
    protected $importCache;

    public function setFields($fields) {
        $this->fields = $fields;
    }

    public function setTable($table) {
        $this->table = $table;
    }

    public function setPrimaryKey($key) {
        $this->key = $key;
    }

    public function setHandler($handler, $callable) {
        $this->handlers[$handler] = $callable;
    }

    public function render() {
        if ($this->importCache) {
            return $this->renderAlignmentForm();
        } else {
            return $this->renderImportFile();
        }
    }

    public function renderImportFile() {
        return '<form action="" method="post" enctype="multipart/form-data">' . Form::renderTokenInput() . '<input type="hidden" name="' . $this->importAction . '" value="import"><input type="file" name="import-file" /><input type="submit" name="submit" value="Submit" class="button"></form>';
    }

    public function validate() {
        $this->loadCSVFromCache();
        $header_row = $this->csv->current();
        if (!$header_row) {
            throw new Exception('No file uploaded');
        }
    }

    protected function loadCSVFromCache($force = false) {
        if (empty($this->importCache) && $cache_key = Request::post('cache')) {
            $this->importCache = new FileCache();
            $this->importCache->loadReference($cache_key);
            if (!$this->importCache->isValid()) {
                throw new Exception('Invalid reference. Please try again.');
            }
        } elseif (empty($this->importCache)) {
            throw new Exception('Invalid reference. Please try again.');
        }

        if (empty($this->csv) || $force) {
            $this->csv = new CSVIterator($this->importCache->getFile());
        }
    }

    public function renderAlignmentForm() {
        $this->loadCSVFromCache();
        $header_row = $this->csv->current();
        $output = '<form action="" method="POST">' . Form::renderTokenInput();
        $output .= '<input type="hidden" name="action" value="' . $this->importAlignAction . '">';
        $output .= '<input type="hidden" name="cache" value="' . $this->importCache->getReference() . '" />';
        $output .= '<table><thead><tr><td>Field</td><td>From CSV Column</td></tr></thead>';

        $input_select = BasicHTML::select('%%', array('-1' => '') + $header_row);

        foreach ($this->fields as $field) {
            if (is_array($field)) {
                $field_string = $field['field'];
                if (!empty($field['display_name'])) {
                    $display_name = $field['display_name'];
                }
            } else {
                $field_string = $field;
                $display_name = ucfirst(str_replace('_', ' ', $field_string));
            }

            if ($field_string != $this->key) {
                $output .= '<tr><td>' . $display_name . '</td><td>'
                    . preg_replace('/%%/', 'alignment[' . $field_string . ']', $input_select) . '</td></tr>';
            }
        }

        $output .= '</table><label><input type="checkbox" name="header" value="1" /> First row is a header, do not import.</label>';

        if (!empty($this->handlers['customImportFields']) && is_callable($this->handlers['customImportFields'])) {
            $output .= call_user_func($this->handlers['customImportFields']);
        } elseif (!empty($this->handlers['customImportFields'])) {
            $output .= $this->handlers['customImportFields'];
        }

        $output .= '<input type="submit" name="submit" value="Submit" class="button" />';

        $output .= '</form>';
        return $output;
    }

    /**
     * Load the uploaded import file into cache and parse it for input variables.
     */
    public function cacheImportFile() {
        // Cache the uploaded file.
        $this->importCache = new FileCache();
        $this->importCache->setName('table import ' . microtime());
        $this->importCache->moveFile('import-file');
    }

    /**
     * Process the data and import it based on alignment fields.
     */
    public function importDataFile() {
        $this->loadCSVFromCache();

        // Load the CSV, skip the first row if it's a header.
        if (Request::post('header', 'int')) {
            $this->csv->next();
        }

        // Process the alignment so we know which fields to import.
        $alignment = Request::get('alignment', 'keyed_array', 'int');
        $fields = array();
        foreach ($alignment as $field => $column) {
            if ($column != -1) {
                $fields[$field] = $column;
            }
        }

        $this->database = Database::getInstance();

        $this->values = array();
        while ($this->csv->valid()) {
            $row = $this->csv->current();
            foreach ($fields as $field => $column) {
                $this->values[$field][] = $row[$column];
            }

            if (count($this->values[$field]) >= 100) {
                $this->processImportBatch();
                $this->values = array();
            }

            $this->csv->next();
        }

        if (!empty($this->values)) {
            $this->processImportBatch();
        }
    }

    protected function processImportBatch() {
        if (!empty($this->table)) {
            // This is a direct import to a database table.
            $last_id = $this->database->insertSets($this->table, array_keys($this->values), $this->values, true);
            if (is_callable($this->handlers['importPostProcess'])) {
                $ids = $last_id ? range($last_id - $this->database->affectedRows() + 1, $last_id) : [];
                call_user_func_array($this->handlers['importPostProcess'], [&$this->values, &$ids]);
            }
        }
        elseif (!empty($this->handlers['importProcess']) && is_callable($this->handlers['importProcess'])) {
            call_user_func_array($this->handlers['importProcess'], [&$this->values]);
        }
        else {
            throw new Exception('No import method declared.');
        }
    }
}
