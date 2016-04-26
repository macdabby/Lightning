<!doctype html>
<?php
use Lightning\Tools\ClientUser;
use Lightning\Tools\Configuration;
use Lightning\Tools\Database;
use Lightning\Tools\Messenger;
use Lightning\Tools\Performance;
use Lightning\View\JS;
use Lightning\View\CSS;
?>
<!--[if IE 9]><html class="lt-ie10" lang="en" > <![endif]-->
<html class="no-js" lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <?= $this->build('metadata'); ?>
    <?= $this->renderHeader(); ?>
</head>
<body class="antialiased hide-extras">
<?php if ($analytics_id = Configuration::get('google_analytics_id')): ?>
    <script>
        (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
                (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
            m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
        })(window,document,'script','//www.google-analytics.com/analytics.js','ga');

        ga('create', '<?=$analytics_id;?>', 'auto');
        ga('send', 'pageview');

    </script>
<?php endif; ?>
<div class="marketing off-canvas-wrap" data-offcanvas>
    <div class="inner-wrap">

        <div class="row">
            <div class="small-12">
                <h1>Welcome!</h1>
            </div>
        </div>
        <div class="row">
            <nav class="top-bar" data-topbar>
                <section class="top-bar-section">
                    <ul class="title-area">
                        <li class="name">
                            <h1><a href="/">Your new site</a></h1>
                        </li>
                        <!-- Remove the class "menu-icon" to get rid of menu icon. Take out "Menu" to just have icon alone -->
                        <li class="toggle-topbar menu-icon"><a href="#"><span>Menu</span></a></li>
                    </ul>
                    <section class="top-bar-section">
                        <ul class="right">
                            <li class="home"><a href="/">Home</a></li>
                            <li class="blog"><a href="/blog">Blog</a></li>
                            <li class="contact"><a href="/contact">Contact</a></li>
                            <li>
                                <?php if (ClientUser::getInstance()->isImpersonating()): ?>
                                    <a href="/user?action=stop-impersonating">Return to Admin User</a>
                                <?php endif; ?>
                                <?php if (ClientUser::getInstance()->id > 0): ?>
                                    <a href="/user?action=logout">Log Out</a>
                                <?php else: ?>
                                    <a href="/user">Log In</a>
                                <?php endif; ?>
                            </li>
                        </ul>
                    </section>
                </section>
            </nav>
            <?php if (ClientUser::getInstance()->isAdmin()): ?>
                <nav class="top-bar" data-topbar>
                    <ul class="title-area">
                        <li class="name">
                            <h1><a href="/">Admin Menu</a></h1>
                        </li>
                        <!-- Remove the class "menu-icon" to get rid of menu icon. Take out "Menu" to just have icon alone -->
                        <li class="toggle-topbar menu-icon"><a href="#"><span>Admin Menu</span></a></li>
                    </ul>
                    <section class="top-bar-section">
                        <ul class="right">
                            <li class="has-dropdown not-click">
                                <a href="/admin/blog/edit">Blog</a>
                                <ul class="dropdown">
                                    <li><a href="/admin/blog/edit">Blog Posts</a></li>
                                    <li><a href="/admin/blog/categories">Blog Categories</a></li>
                                </ul>
                            </li>
                            <li><a href="/admin/pages">Pages</a></li>
                            <li><a href="/admin/users">Users</a></li>
                            <li class="has-dropdown not-click">
                                <a href="/admin/mailing/lists">Mailing</a>
                                <ul class="dropdown">
                                    <li><a href="/admin/mailing/lists">Mailing Lists</a></li>
                                    <li><a href="/admin/mailing/templates">Templates</a></li>
                                    <li><a href="/admin/mailing/messages">Messages</a></li>
                                </ul>
                            </li>
                        </ul>
                    </section>
                </nav>
            <?php endif; ?>
        </div>
        <section role="main" class="scroll-container">
            <div class="row">
                <?php if (empty($full_width)): ?>
                    <div class="medium-8 columns">
                        <?php if (!empty($page_header)): ?>
                            <h1 id="page_header"><?=$page_header?></h1>
                        <?php
                        endif;
                        echo Messenger::renderErrorsAndMessages();
                        if (!empty($content)) :
                            $this->build($content);
                        endif; ?>
                    </div>
                    <div class="small-12 medium-4 columns right-column">
                        <?php $this->build('right_column'); ?>
                    </div>
                <?php else: ?>
                    <div class="large-12 columns">
                        <?php if (!empty($page_header)): ?>
                            <h1 id="page_header"><?=$page_header?></h1>
                        <?php
                        endif;
                        echo Messenger::renderErrorsAndMessages();
                        if (!empty($content)) :
                            $this->build($content);
                        endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <pre>
            <?php
            if (ClientUser::getInstance()->isAdmin()) {
                $database = Database::getInstance();
                print_r($database->getQueries());
                print_r(Performance::timeReport());
            }
            ?>
            </pre>
        </section>
    </div>
</div>
<?= $this->renderFooter(); ?>
</body>
</html>
