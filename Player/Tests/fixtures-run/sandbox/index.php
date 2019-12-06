<?php if (isset($_GET['link'])) : ?>
    <a href="?clicked">This is a link</a>
<?php elseif (isset($_GET['clicked'])) : ?>
    clicked
<?php elseif (isset($_GET['header'])) : ?>
    <?php echo $_SERVER['HTTP_USER_AGENT'].'-'.$_SERVER['PHP_AUTH_USER'].'-'.$_SERVER['PHP_AUTH_PW']; ?>
<?php elseif ('PUT' === $_SERVER['REQUEST_METHOD']) : ?>
    <?php echo file_get_contents('php://input'); ?>
<?php elseif (isset($_GET['form'])) : ?>
    <form action="?" method="post">
        <input type="text" name="firstname">
        <input type="text" name="lastname">
        <input type="file" name="bio">
        <input type="hidden" name="token" value="foo">
        <button type="submit">Submit</button>
    </form>
<?php elseif ('POST' === $_SERVER['REQUEST_METHOD']) : ?>
    <?php echo $_POST['firstname'].'-'.$_POST['lastname'].'-'.$_FILES['bio']['name'].'-'.$_POST['token'].'-'.file_get_contents($_FILES['bio']['tmp_name']); ?>
<?php elseif (isset($_GET['json'])) : ?>
    <?php $json = json_decode(file_get_contents('php://input'), true); ?>
    <?php echo $json['firstname'].'-'.$json['lastname']; ?>
<?php else : ?>
    ok
<?php endif; ?>
