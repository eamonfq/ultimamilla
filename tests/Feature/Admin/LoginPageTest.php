<?php

it('admin login page loads with custom branding', function () {
    $this->get('/admin/login')
        ->assertStatus(200)
        ->assertSee('Panel administrativo')
        ->assertSee('Última Milla Express');
});
