<?php

require_once 'Bot.php';

$bot = new Bot();

while (true) {
    $bot->update();
    sleep(2);
}