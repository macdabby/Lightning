<div class="row">
    <div class="column">
        <form method="post">
            <?= \lightningsdk\core\Tools\Form::renderTokenInput(); ?>
            <h2><?= !empty($confirmationMessage) ? $confirmationMessage : 'Are you sure?'; ?></h2>
            <br><br>
            <input type="hidden" name="values" value="<?= !empty($values) ? \lightningsdk\core\Tools\Scrub::toHTML($values) : ''; ?>">
            <input type="hidden" name="action" value="<?= !empty($successAction) ? $successAction : ''; ?>">
            <a href="<?= !empty($cancelUrl) ? $cancelUrl : '/' ?>" class="button blue medium">
                <?= !empty($cancelText) ? $cancelText : 'Cancel'; ?>
            </a>
            <input type="submit" name="submit" value="<?= !empty($confirmationText) ? $confirmationText : 'Yes'; ?>" class="button red medium">
        </form>
    </div>
</div>
