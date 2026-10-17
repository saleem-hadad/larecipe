<?php

use LaRecipe\Domains\Content\DTOs\SetConfig;
use LaRecipe\Domains\Sources\Enums\Channel;
use LaRecipe\Domains\Sources\Enums\WriteMode;
use LaRecipe\Domains\Sources\Services\ReviewPolicy;

$policy = fn (string $review): ReviewPolicy => new ReviewPolicy(['kb' => SetConfig::fromConfig('kb', ['source' => ['driver' => 'database']], ['review' => $review])]);

it('always publishes changes that ride in a pull request', function (string $review) use ($policy) {
    expect($policy($review)->modeFor('kb', Channel::PullRequest))->toBe(WriteMode::Publish)
        ->and($policy($review)->modeFor('kb', Channel::Migration))->toBe(WriteMode::Publish);
})->with(['required', 'auto']);

it('keeps agent changes made outside a pull request as drafts under review: required', function () use ($policy) {
    expect($policy('required')->modeFor('kb', Channel::RemoteAgent))->toBe(WriteMode::Draft)
        ->and($policy('required')->modeFor('kb', Channel::Sync))->toBe(WriteMode::Draft);
});

it('publishes agent changes made outside a pull request under review: auto', function () use ($policy) {
    expect($policy('auto')->modeFor('kb', Channel::RemoteAgent))->toBe(WriteMode::Publish)
        ->and($policy('auto')->modeFor('kb', Channel::Sync))->toBe(WriteMode::Publish);
});

it('lets people in the admin UI publish, or save a draft when they ask', function () use ($policy) {
    expect($policy('required')->modeFor('kb', Channel::AdminUi))->toBe(WriteMode::Publish)
        ->and($policy('auto')->modeFor('kb', Channel::AdminUi, WriteMode::Draft))->toBe(WriteMode::Draft);
});

it('never lets a remote agent skip review by asking to publish', function () use ($policy) {
    expect($policy('required')->modeFor('kb', Channel::RemoteAgent, WriteMode::Publish))->toBe(WriteMode::Draft);
});

it('treats an unknown review value as required', function () use ($policy) {
    expect($policy('sometimes')->modeFor('kb', Channel::RemoteAgent))->toBe(WriteMode::Draft);
});
