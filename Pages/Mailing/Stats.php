<?php

namespace lightningsdk\core\Pages\Mailing;

use lightningsdk\core\Tools\ChartData;
use lightningsdk\core\Tools\ClientUser;
use lightningsdk\core\Tools\Output;
use lightningsdk\core\Tools\Request;
use lightningsdk\core\Model\Tracker;
use lightningsdk\core\View\Chart\Line;
use lightningsdk\core\View\Field\Time;
use lightningsdk\core\View\JS;

class Stats extends Line {

    protected $ajax = true;

    protected function hasAccess() {
        return ClientUser::requireAdmin();
    }

    public function get() {
        $message_id = Request::get('message_id', Request::TYPE_INT);
        if (empty($message_id)) {
            Output::error('Message Not Found');
        }
        JS::set('chart.' . $this->id . '.params.message_id', ['value' => $message_id]);
        parent::get();
    }

    public function getGetData() {
        $start = Request::get('start', Request::TYPE_INT, null, -30);
        $end = Request::get('end', Request::TYPE_INT, null, 0);
        $message_id = Request::get('message_id', Request::TYPE_INT);

        $email_sent = Tracker::loadOrCreateByName('Email Sent', Tracker::EMAIL)->getHistory(['start' => $start, 'end' => $end, 'sub_id' => $message_id]);
        $email_bounced = Tracker::loadOrCreateByName('Email Bounced', Tracker::EMAIL)->getHistory(['start' => $start, 'end' => $end, 'sub_id' => $message_id]);
        $email_opened = Tracker::loadOrCreateByName('Email Opened', Tracker::EMAIL)->getHistory(['start' => $start, 'end' => $end, 'sub_id' => $message_id]);

        $data = new ChartData(Time::today() + $start, Time::today() + $end);
        $data->addDataSet($email_sent, 'Sent');
        $data->addDataSet($email_bounced, 'Bounced');
        $data->addDataSet($email_opened, 'Opened');

        $data->setXLabels(array_map('jdtogregorian', range(Time::today() + $start, Time::today() + $end)));

        $data->output();
    }
}
