<?php

test('guests are redirected to login from the home page', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});
