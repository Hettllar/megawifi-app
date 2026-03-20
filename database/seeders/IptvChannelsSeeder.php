<?php

namespace Database\Seeders;

use App\Models\IptvChannel;
use Illuminate\Database\Seeder;

class IptvChannelsSeeder extends Seeder
{
    public function run(): void
    {
        $channels = [
            [
                'name' => 'Al Jazeera Arabic',
                'slug' => 'aljazeera-ar',
                'source_url' => 'https://live-hls-web-aja.getaj.net/AJA/index.m3u8',
                'logo' => 'https://upload.wikimedia.org/wikipedia/en/thumb/f/f2/Aljazeera_eng.svg/240px-Aljazeera_eng.svg.png',
                'category' => 'news',
                'language' => 'ar',
                'is_active' => true,
                'sort_order' => 1,
                'description' => 'قناة الجزيرة الإخبارية'
            ],
            [
                'name' => 'Al Arabiya',
                'slug' => 'alarabiya',
                'source_url' => 'https://live.alarabiya.net/alarabiapublish/alarabiya.smil/playlist.m3u8',
                'logo' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/7/7d/Al_Arabiya.svg/240px-Al_Arabiya.svg.png',
                'category' => 'news',
                'language' => 'ar',
                'is_active' => true,
                'sort_order' => 2,
                'description' => 'قناة العربية الإخبارية'
            ],
            [
                'name' => 'Dubai Sports 1',
                'slug' => 'dubai-sports-1',
                'source_url' => 'https://dmitnthvll.cdn.mangomolo.com/dubaisports/smil:dubaisports.smil/playlist.m3u8',
                'logo' => 'https://upload.wikimedia.org/wikipedia/en/4/4b/Dubai_Sports.png',
                'category' => 'sports',
                'language' => 'ar',
                'is_active' => true,
                'sort_order' => 10,
                'description' => 'قناة دبي الرياضية'
            ],
            [
                'name' => 'Abu Dhabi Sports 1',
                'slug' => 'ad-sports-1',
                'source_url' => 'https://admdn1.cdn.mangomolo.com/adsports1/smil:adsports1.stream.smil/playlist.m3u8',
                'logo' => 'https://upload.wikimedia.org/wikipedia/en/4/43/Abu_Dhabi_Sports_Channel_logo.png',
                'category' => 'sports',
                'language' => 'ar',
                'is_active' => true,
                'sort_order' => 11,
                'description' => 'قناة أبوظبي الرياضية'
            ],
            [
                'name' => 'Nile Sport',
                'slug' => 'nile-sport',
                'source_url' => 'https://ythls.armelin.one/channel/UCRf6dBIhnVuS-4nZfAOPz1g.m3u8',
                'logo' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQ8zYpNOdKPZM2CxiBD7HZV1_5x5TqQGZ9vEA&s',
                'category' => 'sports',
                'language' => 'ar',
                'is_active' => true,
                'sort_order' => 12,
                'description' => 'قناة النيل الرياضية'
            ],
            [
                'name' => 'beIN Sports 1 HD',
                'slug' => 'bein-sports-1',
                'source_url' => 'https://example.com/bein1.m3u8',
                'logo' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/7/7c/Bein_sports_logo.svg/240px-Bein_sports_logo.svg.png',
                'category' => 'sports',
                'language' => 'ar',
                'is_active' => false,
                'sort_order' => 20,
                'description' => 'beIN Sports 1 - يحتاج اشتراك مصدر'
            ],
        ];

        foreach ($channels as $channel) {
            IptvChannel::updateOrCreate(
                ['slug' => $channel['slug']],
                $channel
            );
        }
    }
}
