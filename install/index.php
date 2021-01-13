<?php

namespace UKMNorge\SlackApp;

require_once('../env.inc.php');

header("Location: " . SLACK_SHAREABLE_URL);

echo '<script>'
    .'window.location.href = "' . SLACK_SHAREABLE_URL . '";'
    .'</script>';
die();