<?php

namespace lightningsdk\core\Database\Schema;

use lightningsdk\core\Database\Schema;

class Page extends Schema {

    const TABLE = 'page';

    public function getColumns() {
        return [
            'page_id' => $this->autoincrement(),
            'title' => $this->varchar(255),
            'url' => $this->varchar(128),
            'menu_context' => $this->varchar(64),
            'preview_image' => $this->varchar(64),
            'keywords' => $this->varchar(255),
            'description' => $this->varchar(255),
            'template' => $this->varchar(64),
            'body' => $this->text(Schema::MEDIUMTEXT),
            'site_map' => $this->int(true, Schema::TINYINT),
            'frequency' => $this->int(true, Schema::TINYINT),
            'hide_header' => $this->int(true, Schema::TINYINT),
            'hide_menu' => $this->int(true, Schema::TINYINT),
            'hide_social' => $this->int(true, Schema::TINYINT),
            'hide_footer' => $this->int(true, Schema::TINYINT),
            'right_column' => $this->int(true, Schema::TINYINT),
            'full_width' => $this->int(true, Schema::TINYINT),
            'use_parser' => $this->int(true, Schema::TINYINT),
            'priority' => $this->int(true),
            'last_update' => $this->int(true),
            'modules' => $this->text(),
        ];
    }

    public function getKeys() {
        return [
            'primary' => 'page_id',
            'url' => [
                'columns' => ['url'],
                'unique' => true,
            ],
        ];
    }
}
