<?php

namespace lightningsdk\core\Pages\Admin;

use lightningsdk\core\Model\User;
use lightningsdk\core\Pages\Table;
use lightningsdk\core\Tools\ClientUser;
use lightningsdk\core\Tools\Database;
use lightningsdk\core\Tools\Navigation;
use lightningsdk\core\Tools\Request;
use lightningsdk\core\Tools\Session\DBSession;
use lightningsdk\core\View\Field\BasicHTML;
use lightningsdk\core\View\Field\Text;
use lightningsdk\core\Model\Permissions;

class Users extends Table {

    const TABLE = 'user';
    const PRIMARY_KEY = 'user_id';

    protected function hasAccess() {
        return ClientUser::requirePermission(Permissions::EDIT_USERS);
    }

    protected $custom_buttons = [
        'send' => [
            'type' => self::CB_SUBMITANDREDIRECT,
            'text' => 'Save &amp; Impersonate',
            'redirect' => '/admin/users?action=impersonate&id={' . self::PRIMARY_KEY . '}',
        ],
    ];

    protected $searchable = true;
    protected $search_fields = ['email', 'first', 'last', 'user.user_id'];

    protected $filters = [
        'mailing_list' => [
            'class' => 'lightningsdk\\core\\Filter\\MailingList',
        ],
        'roles' => [
            'class' => 'lightningsdk\\core\\Filter\\Roles',
        ],
        'permissions' => [
            'class' => 'lightningsdk\\core\\Filter\\Permissions',
        ],
    ];

    protected $preset = [
        'salt' => [
            'type' => 'hidden',
        ],
        'last_login' => [
            'type' => 'datetime',
            'editable' => false,
        ],
        'created' => [
            'type' => 'datetime',
            'editable' => false,
        ],
        'registered' => [
            'type' => 'datetime',
            'editable' => false,
        ],
    ];

    protected $importable = true;

    protected $links = [
        'roles' => [
            'display_name' => 'Roles',
            'key' => 'role_id',
            'table' => 'role',
            'index' => 'user_role',
            'display_column' => 'name',
            'list' => 'compact',
        ],
        'message_list' => [
            'display_name' => 'Mailing Lists',
            'key' => 'message_list_id',
            'index' => 'message_list_user',
            'display_column' => 'name',
            'list' => 'compact',
        ],
        'user_tag' => [
            'display_name' => 'Tags',
            'key' => 'tag_id',
            'index' => 'user_tag_tag',
            'display_column' => 'name',
            'type' => 'autocomplete',
            'create' => true,
            'list' => 'compact'
        ]
    ];

    protected $action_fields = [
        'impersonate' => [
            'type' => 'link',
            'url' => '/admin/users?action=impersonate&id=',
            'display_value' => '<img src="/images/lightning/user.png" border="0">',
        ],
    ];

    protected $additionalImportFields = ['full_name'];
    protected $processedImportFields = ['first', 'last', 'created'];

    protected function initSettings() {
        $this->preset['password']['submit_function'] = function(&$output) {
            if ($pass = Request::post('password')) {
                $salt = User::getSalt();
                $output['salt'] = bin2hex($salt);
                $output['password'] = User::passHash($pass, $salt);
            }
        };
        $this->preset['password']['edit_value'] = function(&$row) {
            return '';
        };
        $this->preset['password']['display_value'] = function(&$row) {
            return !empty($row['password']) ? 'Set' : '';
        };
        $this->importHandlers = [
            'customImportFields' => [$this, 'customImportFields'],
            'validate' => [$this, 'validateImportRow'],
            'importPostProcess' => [$this, 'importPostProcess'],
        ];
    }

    public function validateImportRow(&$row) {
        // TODO: Scrub email here.
        if (empty($row['email'])) {
            return false;
        }
        if (!empty($row['full_name'])) {
            User::parseNames($row);
        }
        $row['created'] = time();
        return true;
    }

    /**
     * Add mailing list option when importing users.
     *
     * @return string
     */
    public function customImportFields() {
        $all_lists = ['' => ''] + Database::getInstance()->selectColumn('message_list', 'name', [], 'message_list_id');
        $output = 'Add all imported users to this mailing list: ' . BasicHTML::select('message_list_id', $all_lists);
        $output .= 'Or add them to a new mailing list: ' . Text::textField('new_message_list', '');
        return $output;
    }

    public function importPostProcess(&$values, &$ids) {
        static $mailing_list_id;
        $db = Database::getInstance();

        if (!isset($mailing_list_id)) {
            if (!$mailing_list_id = Request::get('message_list_id', Request::TYPE_INT)) {
                // No default list was selected
                if ($new_list = trim(Request::get('new_message_list'))) {
                    $mailing_list_id = $db->insert('message_list', ['name' => $new_list]);
                } else {
                    $mailing_list_id = false;
                }
            }
        }

        $time = time();

        // This will only update users that were just added.
        $db->update(self::TABLE, ['created' => $time], ['user_id' => ['IN', $ids]]);

        // This will add all the users to the mailing list.
        if (!empty($mailing_list_id)) {
            $user_ids = $db->selectColumn(self::TABLE, 'user_id', ['email' => ['IN', $values['email']]]);
            $db->insertMultiple('message_list_user', [
                'user_id' => $user_ids,
                'message_list_id' => $mailing_list_id,
                'time' => $time,
            ], true);
        }
    }

    public function getImpersonate() {
        $session = DBSession::getInstance();
        $session->content->impersonate = Request::get('id', Request::TYPE_INT);
        $session->save();
        // TODO: This should call the User::loginRedirect() function.
        Navigation::redirect('/');
    }
}
