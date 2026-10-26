<?php

use App\Models\Article;

test('the list shows published articles, newest first', function () {
    Article::factory()->create(['title' => 'Part 8: Uranus', 'published_at' => '2026-09-15 09:00']);
    Article::factory()->create(['title' => 'Part 9: Neptune', 'published_at' => '2026-10-20 09:00']);

    $this->get(route('articles.index'))
        ->assertOk()
        ->assertSeeInOrder(['Part 9: Neptune', 'Part 8: Uranus']);
});

test('the list hides drafts and articles with a future date', function () {
    Article::factory()->create(['title' => 'A draft', 'published_at' => null]);
    Article::factory()->create(['title' => 'Coming soon', 'published_at' => now()->addWeek()]);

    $this->get(route('articles.index'))
        ->assertOk()
        ->assertDontSee('A draft')
        ->assertDontSee('Coming soon');
});

test('an article page shows the full article and its author', function () {
    $article = Article::factory()->create([
        'title' => 'Part 9: Neptune',
        'slug' => 'part-9-neptune',
        'author' => 'Jim Hebden',
        'body' => 'Neptune was found by mathematics before anyone saw it.',
        'published_at' => now()->subDay(),
    ]);

    $this->get('/science-articles/part-9-neptune')
        ->assertOk()
        ->assertSee($article->title)
        ->assertSee('Jim Hebden')
        ->assertSee('Neptune was found by mathematics before anyone saw it.');
});

test('a draft article is not found', function () {
    $article = Article::factory()->create(['published_at' => null]);

    $this->get(route('articles.show', $article))->assertNotFound();
});

test('the main menu links to the articles', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('articles.index'));
});
