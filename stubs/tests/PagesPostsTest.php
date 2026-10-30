<?php

use App\Enums\PageType;
use App\Models\Page;
use App\Models\User;

/*
| Acceptance test for the "Pages and posts" feature.
|
| Staff write pages and news posts with the editor. A page or post is public
| when its publish date is in the past, so staff can schedule a post.
*/

test('only staff can open the pages admin', function () {
    $this->get(route('admin.pages.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('admin.pages.index'))
        ->assertForbidden();
});

test('staff see drafts, scheduled posts and published pages in the admin', function () {
    Page::factory()->draft()->create(['title' => 'Summer plans']);
    Page::factory()->post()->scheduled()->create(['title' => 'Eclipse night']);
    Page::factory()->create(['title' => 'About the centre']);

    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.pages.index'))
        ->assertOk()
        ->assertSee(['Summer plans', 'Draft'])
        ->assertSee(['Eclipse night', 'Scheduled'])
        ->assertSee(['About the centre', 'Published']);
});

test('staff write a post with the editor', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.pages.create'))
        ->assertOk()
        ->assertSee('data-slot="editor"', false)
        ->assertSee('name="body"', false);
});

test('staff create a post and the slug is filled from the title', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->post(route('admin.pages.store'), [
            'title' => 'Slime Lab Results',
            'type' => 'post',
            'summary' => 'What we learned about slime.',
            'body' => '<p>It is <strong>sticky</strong>.</p>',
            'published_at' => now()->subHour()->format('Y-m-d\TH:i'),
        ])
        ->assertRedirect(route('admin.pages.index'));

    $post = Page::firstWhere('slug', 'slime-lab-results');

    expect($post)->not->toBeNull()
        ->type->toBe(PageType::Post)
        ->isPublished()->toBeTrue();
});

test('the type must be a page or a post', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->post(route('admin.pages.store'), ['title' => 'Prizes', 'type' => 'banner'])
        ->assertSessionHasErrors('type');
});

test('two pages cannot have the same web address', function () {
    Page::factory()->create(['slug' => 'about']);

    $this->actingAs(User::factory()->staff()->create())
        ->post(route('admin.pages.store'), ['title' => 'About', 'type' => 'page'])
        ->assertSessionHasErrors('slug');
});

test('the body keeps safe HTML only', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->post(route('admin.pages.store'), [
            'title' => 'Robot Day',
            'type' => 'post',
            'body' => '<h2>Robots</h2><p onclick="alert(1)">Bring <strong>batteries</strong>.</p><script>alert(2)</script>',
            'published_at' => now()->subHour()->format('Y-m-d\TH:i'),
        ]);

    $post = Page::firstWhere('slug', 'robot-day');

    expect($post->body)->toBe('<h2>Robots</h2><p>Bring <strong>batteries</strong>.</p>');

    $this->get(route('news.show', $post))
        ->assertOk()
        ->assertSee('<strong>batteries</strong>', false)
        ->assertDontSee('alert(', false);
});

test('staff edit a page, clear its date to make it a draft, and delete it', function () {
    $page = Page::factory()->create(['title' => 'About', 'slug' => 'about']);
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)->get(route('admin.pages.edit', $page))->assertOk()->assertSee('About');

    $this->actingAs($staff)
        ->put(route('admin.pages.update', $page), [
            'title' => 'About the centre',
            'slug' => 'about',
            'type' => 'page',
            'published_at' => '',
        ])
        ->assertRedirect(route('admin.pages.index'));

    expect($page->fresh())
        ->title->toBe('About the centre')
        ->published_at->toBeNull();

    $this->actingAs($staff)
        ->delete(route('admin.pages.destroy', $page))
        ->assertRedirect(route('admin.pages.index'));

    expect($page->fresh())->toBeNull();
});

test('news lists published posts, newest first', function () {
    Page::factory()->post()->create(['title' => 'Older post', 'published_at' => now()->subDays(5)]);
    Page::factory()->post()->create(['title' => 'Newer post', 'published_at' => now()->subDay()]);

    $this->get(route('news.index'))
        ->assertOk()
        ->assertSeeInOrder(['Newer post', 'Older post']);
});

test('news shows ten posts on each page', function () {
    Page::factory()->post()->count(10)->create(['published_at' => now()->subDay()]);
    Page::factory()->post()->create(['title' => 'The oldest post', 'published_at' => now()->subYear()]);

    $this->get(route('news.index'))->assertDontSee('The oldest post');
    $this->get(route('news.index', ['page' => 2]))->assertSee('The oldest post');
});

test('a scheduled post is hidden until its date', function () {
    $post = Page::factory()->post()->create([
        'title' => 'Eclipse night',
        'published_at' => now()->addDays(3),
    ]);

    $this->get(route('news.index'))->assertDontSee('Eclipse night');
    $this->get(route('news.show', $post))->assertNotFound();

    $this->travel(4)->days();

    $this->get(route('news.index'))->assertSee('Eclipse night');
    $this->get(route('news.show', $post))->assertOk()->assertSee('Eclipse night');
});

test('a draft is not public', function () {
    $page = Page::factory()->draft()->create();

    $this->get(route('pages.show', $page))->assertNotFound();
});

test('a page is not listed under news and has its own address', function () {
    $page = Page::factory()->create(['title' => 'Volunteer with us']);

    $this->get(route('news.index'))->assertDontSee('Volunteer with us');
    $this->get(route('pages.show', $page))->assertOk()->assertSee('Volunteer with us');
    $this->get(route('news.show', $page))->assertNotFound();
});

test('a post is not found at a page address', function () {
    $post = Page::factory()->post()->create();

    $this->get(route('pages.show', $post))->assertNotFound();
});

test('the site menu links to the news', function () {
    $this->get(route('home'))->assertSee(route('news.index'));
});
