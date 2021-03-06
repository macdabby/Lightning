<?php

namespace lightningsdk\core\Database\Schema;

use lightningsdk\core\Database\Schema;

class CMS extends Schema {

    const TABLE = 'cms';

    public function getColumns() {
        return [
            'cms_id' => $this->autoincrement(),
            'note' => $this->varchar(255),
            'name' => $this->varchar(128),
            'content' => $this->text(Schema::MEDIUMTEXT),
            'class' => $this->varchar(128),
            'last_modified' => $this->int(true),
        ];
    }

    public function getKeys() {
        return [
            'primary' => 'cms_id',
            'name' => [
                'columns' => ['name'],
                'unique' => true,
            ],
        ];
    }
}
