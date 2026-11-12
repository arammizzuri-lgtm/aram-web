<?php

namespace Tests\Feature;

use App\Filament\Resources\Presentations\Pages\ManagePresentations;
use App\Models\ClientRoom;
use App\Models\Presentation;
use App\Models\PresentationAsset;
use App\Models\PresentationPreset;
use App\Models\PresentationRevision;
use App\Models\Project;
use App\Models\RoomDrawing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Project presentations, the server's side: who may reach them, making one
 * from the list, saving the editor's document untouched, uploads arriving in
 * pieces and stored once, the private files, copies, and what is shared
 * (templates, layouts, branding). The pages and the PDF are made in the
 * browser — tests/js/presentations.test.mjs covers that logic.
 */
class PresentationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret-password']);
    }

    private function url(string $name, array $params = []): string
    {
        return route('filament.studio-aks-panel.presentation-'.$name, $params);
    }

    private function presentation(string $name = 'Ankawa Villa'): Presentation
    {
        return Presentation::create(['name' => $name, 'doc' => Presentation::blank($name, 'minimal-white', 'A3', 'landscape')]);
    }

    private function png(int $w = 40, int $h = 30): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefilledrectangle($im, 0, 0, $w, $h, imagecolorallocate($im, 200, 120, 40));
        ob_start();
        imagepng($im);

        return ob_get_clean();
    }

    /** Send bytes the way the editor does: in order, in pieces, the last one flagged. */
    private function upload(string $bytes, string $name, ?Presentation $for = null, int $piece = 100, ?string $id = null)
    {
        return $this->pieces($this->url('chunk'), $bytes, ['name' => $name, 'presentation' => $for?->id], $piece, $id);
    }

    private function pieces(string $url, string $bytes, array $fields, int $piece = 100, ?string $id = null)
    {
        $id ??= bin2hex(random_bytes(16));
        $response = null;
        foreach (str_split($bytes, $piece) as $i => $chunk) {
            $offset = $i * $piece;
            $response = $this->post($url, $fields + [
                'upload' => $id, 'offset' => $offset, 'last' => $offset + strlen($chunk) >= strlen($bytes) ? 1 : 0,
                'chunk' => UploadedFile::fake()->createWithContent('chunk', $chunk),
            ], ['Accept' => 'application/json']);
        }

        return $response;
    }

    private function project(array $attrs = []): Project
    {
        return Project::create($attrs + ['name' => 'Ankawa House', 'category' => 'residential', 'status' => 'Under Construction', 'size' => 'default', 'is_published' => true]);
    }

    public function test_the_editor_and_its_files_are_only_for_a_signed_in_admin(): void
    {
        $p = $this->presentation();
        $asset = PresentationAsset::import(resource_path('presentations/logo-dehat.png'), 'logo.png', $p->id);

        $this->get($this->url('editor', ['presentation' => $p]))->assertRedirect();
        $this->get($this->url('file', ['hash' => $asset->hash, 'variant' => 'original']))->assertRedirect();
        $this->get($this->url('brand'))->assertRedirect();
        $this->put($this->url('save', ['presentation' => $p]), ['name' => 'x', 'doc' => '{"pages":[]}'])->assertRedirect();
        $this->assertSame('Ankawa Villa', $p->fresh()->name);
    }

    public function test_a_presentation_is_made_from_the_list_and_opens_in_the_editor(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(ManagePresentations::class)
            ->assertSee('No presentations yet')
            ->callAction('create', ['name' => 'Kani Villa', 'theme' => 'editorial', 'size' => 'A4', 'orient' => 'portrait'])
            ->assertHasNoActionErrors();

        $p = Presentation::sole();
        $this->assertSame(['v' => 1, 'size' => 'A4', 'orient' => 'portrait', 'theme' => 'editorial', 'lang' => 'en', 'info' => ['project' => 'Kani Villa'], 'pages' => []], json_decode($p->doc, true));

        // the editor page: the modules it loads, and everything it needs to start
        $this->get($p->editorUrl())->assertOk()
            ->assertSee('type="importmap"', false)->assertSee('/js/presentations/editor.js?v=', false)
            ->assertSee('"theme":"editorial"', false)->assertSee('Dehat Construction', false)
            ->assertSee('Kani Villa');
        // the branding was made on that first visit, with the two logos that ship with the code
        $brand = json_decode(PresentationPreset::brand()->data, true);
        $this->assertSame(2, PresentationAsset::whereNull('presentation_id')->count());
        $this->assertSame('dehat', $brand['partners'][0]['id']);
        Storage::disk('local')->assertExists('presentations/originals/'.$brand['logo'].'.png');
    }

    public function test_saving_keeps_the_document_exactly_and_the_list_finds_it_by_project_or_client(): void
    {
        $this->actingAs($this->admin());
        $p = $this->presentation('Draft');
        // an empty object must come back an object, not a list
        $doc = '{"v":1,"info":{"project":"Ankawa Villa","client":"Sarbast Karim"},"pages":[{"id":"a","layout":"cover","ov":{},"opts":{}},{"id":"b","layout":"closing","ov":{}}]}';

        $this->putJson($this->url('save', ['presentation' => $p]), ['name' => 'Ankawa — concept', 'doc' => $doc])->assertOk()->assertJsonStructure(['saved_at']);
        $p->refresh();
        $this->assertSame($doc, $p->doc);
        $this->assertSame(['Ankawa — concept', 'Ankawa Villa', 'Sarbast Karim', 2], [$p->name, $p->project_name, $p->client_name, $p->page_count]);
        $this->get($p->editorUrl())->assertSee('"ov":{}', false);

        $other = $this->presentation('Something else');
        Livewire::test(ManagePresentations::class)
            ->searchTable('Sarbast')->assertCanSeeTableRecords([$p])->assertCanNotSeeTableRecords([$other])
            ->searchTable('Ankawa Villa')->assertCanSeeTableRecords([$p]);

        // what is not a document is refused, and nothing is overwritten
        $this->putJson($this->url('save', ['presentation' => $p]), ['name' => 'x', 'doc' => 'not json'])->assertStatus(422);
        $this->putJson($this->url('save', ['presentation' => $p]), ['name' => 'x', 'doc' => '{"info":{}}'])->assertStatus(422);
        $this->assertSame($doc, $p->fresh()->doc);
    }

    public function test_an_upload_arrives_in_pieces_and_is_stored_once_under_its_hash(): void
    {
        $this->actingAs($this->admin());
        $p = $this->presentation();
        $bytes = $this->png(64, 48);
        $hash = hash('sha256', $bytes);

        $asset = $this->upload($bytes, 'ground floor.png', $p)->assertOk()->json('asset');
        $this->assertSame([$hash, 'image', 'png', 'ground floor.png', strlen($bytes)], [$asset['hash'], $asset['kind'], $asset['ext'], $asset['name'], $asset['size']]);
        $this->assertSame(['w' => 64, 'h' => 48], $asset['meta']);
        // the original, byte for byte
        $this->assertSame($bytes, Storage::disk('local')->get("presentations/originals/{$hash}.png"));
        $this->assertSame([], Storage::disk('local')->files('presentations/tmp'));

        // the same file again is the same asset, not a second copy
        $again = $this->upload($bytes, 'ground floor (2).png', $p)->assertOk()->json('asset');
        $this->assertSame($asset['id'], $again['id']);
        $this->assertSame(1, PresentationAsset::count());

        // a piece out of place is refused rather than joined wrongly
        $this->post($this->url('chunk'), ['upload' => str_repeat('a', 32), 'offset' => 500, 'last' => 0, 'name' => 'x.png',
            'chunk' => UploadedFile::fake()->createWithContent('chunk', 'abc')], ['Accept' => 'application/json'])->assertStatus(409);
    }

    public function test_a_file_that_only_claims_to_be_an_image_is_refused(): void
    {
        $this->actingAs($this->admin());

        $this->upload('<?php echo "not an image"; ?>', 'render.jpg', $this->presentation())
            ->assertStatus(422)->assertJson(['message' => 'Only JPG and PNG images and PDF files can be used.']);
        $this->assertSame(0, PresentationAsset::count());
        $this->assertSame([], Storage::disk('local')->allFiles('presentations'));

        // a PDF is known by its first bytes too
        $pdf = $this->upload("%PDF-1.7\n1 0 obj\n<<>>\nendobj\n%%EOF", 'plans.pdf', $this->presentation('B'))->assertOk()->json('asset');
        $this->assertSame(['pdf', 'pdf'], [$pdf['kind'], $pdf['ext']]);
    }

    public function test_previews_are_kept_beside_the_original_and_files_are_served_privately(): void
    {
        $this->actingAs($this->admin());
        $p = $this->presentation();
        $pdf = PresentationAsset::find($this->upload("%PDF-1.7\n%%EOF", 'plans.pdf', $p)->json('asset.id'));

        $sent = $this->post($this->url('previews', ['asset' => $pdf]), [
            'page' => 2,
            'preview' => UploadedFile::fake()->image('preview.jpg', 120, 80),
            'thumb' => UploadedFile::fake()->image('thumb.jpg', 30, 20),
            'meta' => json_encode(['pages' => [['w' => 2383.94, 'h' => 1683.78], ['w' => 1190.55, 'h' => 841.89]], 'anything' => 'else']),
        ], ['Accept' => 'application/json'])->assertOk()->json('asset.meta');
        $this->assertSame(['pages' => [['w' => 2383.94, 'h' => 1683.78], ['w' => 1190.55, 'h' => 841.89]], 'previews' => [2]], $sent);

        $file = fn (string $variant, array $q = []) => $this->get($this->url('file', ['hash' => $pdf->hash, 'variant' => $variant] + $q));
        $preview = $file('preview', ['page' => 2])->assertOk();
        $this->assertStringContainsString('private', $preview->headers->get('Cache-Control'));
        $this->assertSame('image/jpeg', $preview->headers->get('Content-Type'));
        $this->assertSame('application/pdf', $file('original')->assertOk()->headers->get('Content-Type'));
        $file('preview', ['page' => 1])->assertNotFound();   // never made
        $file('secret')->assertNotFound();
        $this->get($this->url('file', ['hash' => str_repeat('0', 64), 'variant' => 'original']))->assertNotFound();
        $this->get($this->url('file', ['hash' => '..', 'variant' => 'original']))->assertNotFound();
    }

    public function test_a_copy_shares_the_stored_files_and_a_file_goes_with_its_last_presentation(): void
    {
        $this->actingAs($this->admin());
        $p = $this->presentation();
        $asset = PresentationAsset::find($this->upload($this->png(), 'render.png', $p)->json('asset.id'));
        $this->post($this->url('previews', ['asset' => $asset]), ['page' => 0,
            'preview' => UploadedFile::fake()->image('p.jpg', 20, 20), 'thumb' => UploadedFile::fake()->image('t.jpg', 10, 10)])->assertOk();
        $original = $asset->originalPath();

        Livewire::test(ManagePresentations::class)->callTableAction('duplicate', $p);
        $copy = Presentation::where('name', 'Ankawa Villa (copy)')->sole();
        $this->assertSame($p->doc, $copy->doc);
        $this->assertSame([$asset->hash], $copy->assets->pluck('hash')->all());
        $mine = fn () => array_values(array_filter(Storage::disk('local')->allFiles('presentations'), fn ($f) => str_contains($f, $asset->hash)));
        $this->assertCount(3, $mine());   // one original, its preview and thumbnail — not two of each

        // deleting the first leaves the copy whole
        Livewire::test(ManagePresentations::class)->callTableAction('delete', $p);
        $this->assertModelMissing($p);
        Storage::disk('local')->assertExists($original);
        $this->get($this->url('file', ['hash' => $asset->hash, 'variant' => 'thumb']))->assertOk();

        // with the last one, the file and its previews go
        $copy->delete();
        $this->assertSame([], $mine());
        $this->assertSame(0, PresentationAsset::whereNotNull('presentation_id')->count());

        // an unused file can be removed from a presentation on its own
        $q = $this->presentation('Q');
        $id = $this->upload($this->png(), 'render.png', $q)->json('asset.id');
        $this->delete($this->url('asset', ['asset' => $id]))->assertNoContent();
        $this->assertSame([], $mine());
    }

    public function test_templates_layouts_and_branding_are_saved_as_sent(): void
    {
        $this->actingAs($this->admin());

        $theme = $this->postJson($this->url('presets'), ['type' => 'theme', 'name' => 'Studio dark', 'data' => '{"name":"Studio dark","colors":{"bg":"#101010"},"extra":{}}'])
            ->assertOk()->json('preset');
        $this->assertSame(['theme', 'Studio dark', '#101010'], [$theme['type'], $theme['name'], $theme['data']['colors']['bg']]);
        $this->assertSame('{"name":"Studio dark","colors":{"bg":"#101010"},"extra":{}}', PresentationPreset::find($theme['id'])->data);
        $this->postJson($this->url('presets'), ['type' => 'brand', 'name' => 'x', 'data' => '{}'])->assertStatus(422);
        $this->postJson($this->url('presets'), ['type' => 'layout', 'name' => 'x', 'data' => 'nope'])->assertStatus(422);

        // a saved template is offered for a new presentation, and can start one
        Livewire::test(ManagePresentations::class)
            ->callAction('create', ['name' => 'In my template', 'theme' => 'saved:'.$theme['id'], 'size' => 'A3', 'orient' => 'landscape'])
            ->assertHasNoActionErrors();
        $this->assertSame('saved:'.$theme['id'], json_decode(Presentation::sole()->doc, true)['theme']);
        $this->get($this->url('brand'))->assertOk()->assertSee('Studio dark');

        $brand = json_decode(PresentationPreset::brand()->data, true);
        $brand['studio'] = 'Aram Mizuri Architect';
        $this->putJson($this->url('brand-save'), ['data' => json_encode($brand)])->assertOk();
        $this->assertSame('Aram Mizuri Architect', json_decode(PresentationPreset::brand()->data, true)['studio']);
        $this->assertSame(1, PresentationPreset::where('type', 'brand')->count());

        $this->delete($this->url('preset', ['preset' => $theme['id']]))->assertNoContent();
        $this->delete($this->url('preset', ['preset' => PresentationPreset::brand()->id]))->assertNotFound();   // the branding is not a preset to delete
        $this->assertSame(['brand'], PresentationPreset::pluck('type')->all());
    }

    public function test_a_presentation_for_a_website_project_starts_with_its_details_and_its_uploaded_pictures(): void
    {
        $this->actingAs($this->admin());
        Storage::fake('public');
        $public = Storage::disk('public');
        $public->put('projects/front.png', $this->png(60, 40));
        $im = imagecreatetruecolor(50, 30);
        ob_start();
        imagewebp($im);
        $public->put('projects/garden.webp', ob_get_clean());
        $public->put('projects/broken.webp', 'not an image');
        $project = $this->project([
            'name_ku' => 'خانووی عەنکاوە', 'typology' => 'Villa', 'location' => 'Ankawa, Erbil', 'area' => '1,250 m²',
            'desc' => 'The brief', 'desc_ku' => 'کورتەی پرۆژە', 'cover' => 'projects/garden.webp',
            'imgs' => ['projects/front.png', 'https://example.com/elsewhere.jpg', 'projects/garden.webp', 'projects/missing.jpg', 'projects/broken.webp', 'projects/front.png'],
        ]);
        ClientRoom::create(['project_id' => $project->id, 'client_name' => 'Shilan', 'lang' => 'ku']);

        Livewire::test(ManagePresentations::class)
            ->callAction('create', ['project' => $project->id, 'lang' => 'ku', 'theme' => 'editorial', 'size' => 'A3', 'orient' => 'landscape'])
            ->assertHasNoActionErrors();

        $p = Presentation::sole();
        $doc = json_decode($p->doc, true);
        $this->assertSame('خانووی عەنکاوە', $p->name);   // the project's name, in the language chosen
        $this->assertSame('ku', $doc['lang']);
        $this->assertSame(['project' => 'خانووی عەنکاوە', 'client' => 'Shilan', 'location' => 'Ankawa, Erbil', 'type' => 'Villa',
            'plotArea' => '1250', 'stage' => 'لەژێر بنیاتنان', 'date' => now()->toDateString()], $doc['info']);
        $this->assertSame('Shilan', $p->client_name);

        // its own pictures only — once each; the WebP made a JPEG; nothing from elsewhere, missing or unreadable
        $assets = $p->assets()->orderBy('id')->get();
        $this->assertSame([['front.png', 'png'], ['garden.jpg', 'jpg']], $assets->map(fn ($a) => [$a->name, $a->ext])->all());
        $this->assertSame(['w' => 50, 'h' => 30], $assets[1]->meta);
        $this->assertSame(['cover' => $assets[1]->hash, 'images' => $assets->pluck('hash')->all(), 'body' => 'کورتەی پرۆژە'], $doc['seed']);
        Storage::disk('local')->assertExists($assets[1]->originalPath());
        // the editor gets them without previews, and makes those itself
        $this->get($p->editorUrl())->assertOk()->assertSee($assets[1]->hash);
    }

    public function test_a_revision_keeps_the_presentation_as_saved_and_the_files_it_shows(): void
    {
        $this->actingAs($this->admin());
        $p = $this->presentation();
        $asset = PresentationAsset::find($this->upload($this->png(), 'render.png', $p)->json('asset.id'));
        $doc = '{"v":1,"info":{"project":"Ankawa Villa","revision":"A"},"revisions":[{"id":"k1","rev":"A"}],"pages":[{"layout":"image-full","images":[{"asset":"'.$asset->hash.'"}]}]}';
        $this->putJson($this->url('save', ['presentation' => $p]), ['name' => 'Ankawa Villa', 'doc' => $doc])->assertOk();

        $rev = $this->postJson($this->url('revisions', ['presentation' => $p]), ['key' => 'k1', 'label' => 'A', 'note' => 'First issue'])
            ->assertOk()->json('revision');
        $this->assertSame(['k1', 'A', 'First issue'], [$rev['key'], $rev['label'], $rev['note']]);
        $this->assertSame($doc, PresentationRevision::find($rev['id'])->doc);
        $this->get($p->editorUrl())->assertOk()->assertSee('"label":"A"', false);

        // the page changes afterwards; the revision is as it was
        $this->putJson($this->url('save', ['presentation' => $p]), ['name' => 'Ankawa Villa', 'doc' => '{"v":1,"pages":[]}'])->assertOk();
        $this->assertSame(json_decode($doc, true), $this->getJson($this->url('revision', ['revision' => $rev['id']]))->assertOk()->json('doc'));

        // a file the revision shows is kept, though no page uses it now
        $this->deleteJson($this->url('asset', ['asset' => $asset]))->assertStatus(422);
        $this->assertModelExists($asset);

        // a copy: a presentation of its own, as it was, with its files
        $url = $this->postJson($this->url('revision-copy', ['revision' => $rev['id']]))->assertOk()->json('url');
        $copy = Presentation::where('name', 'Ankawa Villa — Rev A')->sole();
        $this->assertSame($copy->editorUrl(), $url);
        $this->assertSame($doc, $copy->doc);
        $this->assertSame(1, $copy->page_count);
        $this->assertSame([$asset->hash], $copy->assets->pluck('hash')->all());

        $this->delete($this->url('revision-delete', ['revision' => $rev['id']]))->assertNoContent();
        $this->deleteJson($this->url('asset', ['asset' => $asset]))->assertNoContent();
        // and they go with their presentation
        $copy->revisions()->create(['label' => 'B', 'doc' => '{}']);
        $copy->delete();
        $this->assertSame(0, PresentationRevision::count());
    }

    public function test_an_exported_pdf_is_sent_to_a_client_room_to_wait_for_approval(): void
    {
        $this->actingAs($this->admin());
        $p = $this->presentation();
        $room = ClientRoom::create(['project_id' => $this->project()->id, 'client_name' => 'Shilan', 'client_phone' => '0750 123 4567', 'lang' => 'en']);
        $this->get($p->editorUrl())->assertOk()->assertSee('Shilan — Ankawa House');

        $pdf = "%PDF-1.7\n".str_repeat('x', 450)."\n%%EOF";
        $sent = $this->pieces($this->url('to-room', ['presentation' => $p]), $pdf,
            ['name' => 'Ankawa: Villa.pdf', 'room' => $room->id, 'title' => 'Ankawa Villa — design', 'note' => 'For Thursday'])->assertOk()->json();
        $this->assertSame('Shilan', $sent['client']);
        $this->assertStringContainsString('/client-rooms/'.$room->id, $sent['room']);
        $this->assertStringStartsWith('https://wa.me/9647501234567?text=', $sent['whatsapp']);
        $this->assertStringContainsString(rawurlencode($room->url()), $sent['whatsapp']);

        $drawing = RoomDrawing::sole();
        $this->assertSame([$room->id, 'Ankawa Villa — design', 'For Thursday', RoomDrawing::PENDING, 'Ankawa Villa.pdf'],
            [$drawing->client_room_id, $drawing->title, $drawing->note, $drawing->status, $drawing->file_name]);
        $this->assertSame($pdf, Storage::disk('local')->get($drawing->file_path));
        $this->assertSame([], Storage::disk('local')->files('presentations/tmp'));

        // only a PDF
        $this->pieces($this->url('to-room', ['presentation' => $p]), $this->png(), ['name' => 'x.pdf', 'room' => $room->id, 'title' => 'x'])->assertStatus(422);
        $this->assertSame(1, RoomDrawing::count());
    }
}
