<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Made-up authors and posts for local development, in the three languages the
 * site has to handle. Never run this against a real database:
 *
 *   php artisan db:seed --class=DemoContentSeeder
 */
class DemoContentSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $aziza = $this->author('aziza', 'Aziza Karimova', 'Toshkent haqida qisqa eslatmalar va suratlar. Demo muallif.', '@aziza_notes');
        $dmitry = $this->author('dmitry', 'Дмитрий Орлов', 'Пишу о городе, книгах и о том, как устроены вещи. Демо-автор.', '@orlov_writes');
        $oybek = $this->author('oybek', 'Oybek Rahimov', 'Notes on building small software for big cities. Demo author.', '@oybek_dev');

        $this->post($aziza, 'Chorsu bozorida bir tong', 'gallery', 2, $this->html([
            'Erta tongda bozor hali uyg‘onmagan, lekin non sotuvchilar allaqachon joyida.',
            'Bu albomda oʻsha tongning besh lahzasi bor.',
        ]), media: 5);

        $this->post($aziza, 'Kuzgi Toshkent: sakkizta joy', 'article', 6, $this->html([
            'Kuz Toshkentning eng yaxshi fasli. Havo salqin, daraxtlar sarg‘ayadi, shahar sekinlashadi.',
            '### Anhor boʻyi',
            'Ertalab yugurish uchun eng yaxshi joy. Suvning ovozi shahar shovqinini bosadi.',
            '### Eski shahar',
            'Tor koʻchalar, ganch naqshlar va har burchakda choyxona. Bu yerda vaqt boshqacha oqadi.',
        ]), cover: true);

        $this->post($aziza, 'Bugun metroda bir kitob ko‘rdim', 'article', 9, $this->html([
            'Qarshimda oʻtirgan qiz «Oʻtkan kunlar»ni oʻqiyotgan edi. Yuz yil oʻtsa ham bu kitob oʻz oʻquvchisini topadi.',
        ]));

        $this->post($dmitry, 'Почему старые дворы Ташкента тише новых кварталов', 'article', 1, $this->html([
            'Если пройтись от Чиланзара до Юнусабада, можно заметить странную вещь: в старых дворах тише, хотя людей там не меньше.',
            '### Деревья как архитектура',
            'Чинары во дворах сажали ещё в шестидесятых. Сегодня их кроны работают как потолок: гасят шум, держат тень, собирают двор в одну комнату под открытым небом.',
            '> Двор — это не пространство между домами. Это ещё одна комната, которую жильцы делят на всех.',
            '### Масштаб',
            'Пятиэтажка не давит. Из окна пятого этажа ещё можно позвать ребёнка домой, и он услышит.',
            'Новые кварталы строят выше и плотнее. Это экономически понятно, но двор в них становится парковкой, а парковка не умеет быть комнатой.',
            '### Что можно сделать',
            'Сохранять деревья при реконструкции, не закрывать дворы для машин целиком, оставлять скамейки там, где люди уже сидят.',
            'Звучит скромно, но именно из таких решений и складывается тишина.',
        ]), cover: true, telegraph: true);

        $this->post($dmitry, 'Короткая заметка о дожде', 'article', 4, $this->html([
            'Первый дождь осени пахнет пылью и горячим асфальтом. Через час — уже листьями.',
        ]));

        $this->post($dmitry, 'Книжный развал у Дома печати', 'gallery', 8, $this->html([
            'Субботний развал: старые издания, открытки, пластинки.',
        ]), media: 7);

        $this->post($dmitry, 'Что я читаю в октябре', 'article', 12, $this->html([
            'Три книги на месяц: одна о городе, одна о памяти и одна просто для удовольствия.',
            '- «Город и его тени»',
            '- «Память места»',
            '- Сборник рассказов, который давно лежал на полке',
        ]));

        $this->post($oybek, 'Shipping a bus-arrival bot in a weekend', 'article', 3, $this->html([
            'Tashkent buses publish no live feed, but drivers check in at terminals. That turned out to be enough.',
            '### The data',
            'Terminal check-ins give a departure time. Average segment times give everything else, with an honest error bar.',
            '### The bot',
            'One command, one stop, one answer: the next two buses and how sure the estimate is.',
        ]), cover: true);

        $this->post($oybek, 'A tiny rule for naming things', 'article', 10, $this->html([
            'Name a thing after what a reader will look for, not after how it works inside.',
        ]));
    }

    private function author(string $username, string $name, string $bio, string $channel): User
    {
        return User::updateOrCreate(['username' => $username], [
            'name' => $name,
            'email' => "{$username}@demo.test",
            'password' => Hash::make('password'),
            'bio' => $bio,
            'avatar' => "https://i.pravatar.cc/240?u={$username}",
            'telegram_channel' => $channel,
        ]);
    }

    private function post(User $author, string $title, string $type, int $daysAgo, string $html, int $media = 0, bool $cover = false, bool $telegraph = false): void
    {
        $slug = Str::slug($title) ?: 'post-'.$daysAgo;

        $images = collect($media > 0 ? range(1, $media) : [])
            ->map(fn (int $i) => "https://picsum.photos/seed/{$author->username}-{$slug}-{$i}/1600/1067")
            ->values();

        $post = Post::updateOrCreate(['user_id' => $author->id, 'slug' => $slug], [
            'title' => $title,
            'html' => $html,
            'excerpt' => Post::excerptFrom($html),
            'post_type' => $type,
            'status' => 'published',
            'source' => Post::SOURCE_TELEGRAM,
            'cover_image' => $images->first() ?? ($cover ? "https://picsum.photos/seed/{$author->username}-{$slug}/1600/900" : null),
            'telegraph_url' => $telegraph ? "https://telegra.ph/{$slug}" : null,
            'published_at' => now()->subDays($daysAgo)->setTime(9 + $daysAgo % 8, 15),
        ]);

        $post->media()->delete();
        foreach ($images as $position => $url) {
            $post->media()->create(['url' => $url, 'type' => 'photo', 'position' => $position]);
        }
    }

    /** Paragraphs, "### " headings, "> " quotes and "- " list items to HTML. */
    private function html(array $blocks): string
    {
        $out = '';
        $list = [];

        foreach ($blocks as $block) {
            if (str_starts_with($block, '- ')) {
                $list[] = '<li>'.e(substr($block, 2)).'</li>';

                continue;
            }
            if ($list) {
                $out .= '<ul>'.implode('', $list).'</ul>';
                $list = [];
            }

            $out .= match (true) {
                str_starts_with($block, '### ') => '<h3>'.e(substr($block, 4)).'</h3>',
                str_starts_with($block, '> ') => '<blockquote><p>'.e(substr($block, 2)).'</p></blockquote>',
                default => '<p>'.e($block).'</p>',
            };
        }

        return $out.($list ? '<ul>'.implode('', $list).'</ul>' : '');
    }
}
