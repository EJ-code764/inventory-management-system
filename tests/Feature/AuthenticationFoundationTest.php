<?php

test('guests can view the login page', function () {
    $response = $this->get(route('login'));

    $response->assertStatus(200);
});
