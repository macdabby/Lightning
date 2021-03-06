<?php

namespace lightningsdk\core\Database\Schema;

use lightningsdk\core\Database\Schema;

class Message extends Schema {

    const TABLE = 'message';

    public function getColumns() {
        return [
            'message_id' => $this->autoincrement(),
            'subject' => $this->varchar(255),
            'body' => $this->text(),
            'template_id' => $this->int(true),
            'send_date' => $this->int(true),
            'never_resend' => $this->int(Schema::TINYINT),
            'note' => $this->varchar(255),
        ];
    }

    public function getKeys() {
        return [
            'primary' => 'message_id',
        ];
    }
}
