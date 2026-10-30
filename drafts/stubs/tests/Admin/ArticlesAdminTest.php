<?php

use App\Models\Article;
use App\Models\User;

test('guests are sent to the login page', function () {
    $this->get(route('admin.articles.index'))->assertRedirect(route('login'));
});

test('users who are not staff cannot manage articles', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.articles.index'))
        ->assertForbidden();
});

test('staff see every article, including drafts', function () {
    Article::factory()->create(['title' => 'Part 9: Neptune']);
    Article::factory()->create(['title' => 'Part 10: Pluto', 'published_at' => null]);

    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.articles.index'))
        ->assertOk()
        ->assertSee('Part 9: Neptune')
        ->assertSee('Part 10: Pluto');
});

test('staff can create a draft and the slug is filled from the title', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->post(route('admin.articles.store'), [
            'title' => 'Part 10: Pluto',
            'author' => 'Jim Hebden',
            'excerpt' => 'Is Pluto a planet?',
            'body' => 'Pluto was named by an eleven-year-old.',
        ])
        ->assertRedirect();

    expect(Article::sole())
        ->slug->toBe('part-10-pluto')
        ->published_at->toBeNull();
});

test('staff can publish an article', function () {
    $article = Article::factory()->create(['published_at' => null]);

    $this->actingAs(User::factory()->staff()->create())
        ->put(route('admin.articles.update', $article), [
            'title' => $article->title,
            'author' => $article->author,
            'excerpt' => $article->excerpt,
            'body' => $article->body,
            'published_at' => '2026-11-02 09:00',
        ])
        ->assertRedirect();

    expect($article->fresh()->published_at->toDateString())->toBe('2026-11-02');
});

test('two articles cannot have the same slug', function () {
    Article::factory()->create(['slug' => 'part-10-pluto']);

    $this->actingAs(User::factory()->staff()->create())
        ->post(route('admin.articles.store'), [
            'title' => 'Part 10: Pluto',
            'author' => 'Jim Hebden',
            'excerpt' => 'Is Pluto a planet?',
            'body' => 'Pluto was named by an eleven-year-old.',
        ])
        ->assertSessionHasErrors('slug');
});

test('staff can delete an article', function () {
    $article = Article::factory()->create();

    $this->actingAs(User::factory()->staff()->create())
        ->delete(route('admin.articles.destroy', $article))
        ->assertRedirect(route('admin.articles.index'));

    expect(Article::count())->toBe(0);
});
