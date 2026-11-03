<?php

it('sends a fresh install to the setup page', function () {
    $this->get('/')->assertRedirect('/setup');
});
