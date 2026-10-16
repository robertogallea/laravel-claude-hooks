<?php

it('loads the package configuration', function () {
    expect(config('claude-hooks.hooks'))->toBeArray();
});
