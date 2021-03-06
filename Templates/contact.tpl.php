<?php

use lightningsdk\core\Tools\Form;
use lightningsdk\core\View\Field;
use lightningsdk\core\Tools\ReCaptcha;

?>
<div class="row">
    <h1>Contact</h1>

    <form action="/contact" method="post" id="contact_form" data-abide>
        <?= Form::renderTokenInput(); ?>
        <div>
            <label>Your Name:
                <input type="text" name="name" id='name' value="<?=Field::defaultValue('name');?>" required />
            </label>
            <small class="form-error">Please enter your name.</small>
        </div>

        <div>
            <label>
                Your Email:
                <input type="email" name="email" id='my_email' value="<?=Field::defaultValue('email');?>" required />
            </label>
            <small class="form-error">Please enter a valid email address.</small>
        </div>

        <div>
            <label>
                Your message:
                <textarea name="message" cols="70" rows="5"><?=Field::defaultValue('name', null, 'text');?></textarea><br />
            </label>
        </div>
        <input name="contact" type="hidden" value="true" />
        <?php if (\lightningsdk\core\Tools\Configuration::get('recaptcha.invisible.public')) : ?>
            <?=ReCaptcha::renderInvisible('Send Message', 'button');?>
        <?php elseif (\lightningsdk\core\Tools\Configuration::get('recaptcha.public')): ?>
            <?=ReCaptcha::render()?>
            <br />
            <input type="Submit" name="Submit" value="Send Message" class="button" />
        <?php else: ?>
            <input type="Submit" name="Submit" value="Send Message" class="button" />
        <?php endif; ?>
    </form>
</div>
