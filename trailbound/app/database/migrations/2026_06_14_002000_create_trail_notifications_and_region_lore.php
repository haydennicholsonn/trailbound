<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trail_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 80);
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('action', 80)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at', 'created_at']);
        });

        Schema::table('regions', function (Blueprint $table) {
            $table->string('real_name')->nullable()->after('name');
            $table->json('facts')->nullable()->after('summary');
        });

        $lore = [
            'city-bowl' => ['real_name' => 'Cape Town CBD, Gardens, Bo-Kaap, Vredehoek, Woodstock', 'facts' => ['Home shard with dense climbs, stair streets, and fast lunch-loop routes.', 'Best for first unlocks, XP farming, and short social runs.']],
            'atlantic-edge' => ['real_name' => 'Sea Point, Green Point, Camps Bay, Clifton, V&A Waterfront', 'facts' => ['Promenade rhythm, ocean wind, and highly shareable route views.', 'Coastal quests favor consistency and tempo pacing.']],
            'table-crown' => ['real_name' => 'Table Mountain, Devil’s Peak, Newlands Forest, Kirstenbosch', 'facts' => ['High difficulty mountain shard with elevation-heavy rewards.', 'Best suited to endurance quests and rare item drops.']],
            'southern-vines' => ['real_name' => 'Constantia, Wynberg, Claremont, Rondebosch, Newlands', 'facts' => ['Leafy suburb loops with steady mileage chains.', 'Balanced biome for weekly and monthly challenge progress.']],
            'false-bay' => ['real_name' => 'Muizenberg, Kalk Bay, Fish Hoek, Simon’s Town, Lakeside', 'facts' => ['Tidal routes and exposed wind sections make pacing matter.', 'Quest chains lean into distance and coastal exploration.']],
            'northern-grid' => ['real_name' => 'Bellville, Parow, Goodwood, Milnerton, Century City', 'facts' => ['Practical road-grid shard with reliable distance routes.', 'Great for friend challenges and repeatable workday runs.']],
            'west-coast-watch' => ['real_name' => 'Table View, Bloubergstrand, Big Bay, Parklands, Melkbosstrand', 'facts' => ['Long beachfront straights with Table Mountain sightlines.', 'Unlocks favor distance streaks and low-friction route discovery.']],
            'tygerberg-spine' => ['real_name' => 'Tygerberg, Welgemoed, Kenridge, Plattekloof, Panorama', 'facts' => ['Northern elevation pocket with rolling climbs.', 'Medium quests reward hill consistency.']],
            'durbanville-crown' => ['real_name' => 'Durbanville, Vierlanden, Sonstraal, Uitzicht', 'facts' => ['Vineyard roads and rolling gradients.', 'A premium biome for endurance and route variety.']],
            'industrial-anvil' => ['real_name' => 'Epping, Maitland, Goodwood, Elsies River, Bishop Lavis', 'facts' => ['Steel-flat utility roads and city-engine grit.', 'Easy shard for practical unlocks and short errands turned runs.']],
            'southern-line' => ['real_name' => 'Mowbray, Rosebank, Rondebosch, Claremont, Kenilworth, Wynberg', 'facts' => ['Rail-adjacent everyday routes with social density.', 'Starter-friendly and strong for beginner quest chains.']],
            'constantia-greenbelt' => ['real_name' => 'Constantia, Meadowridge, Bergvliet, Diep River', 'facts' => ['Greenbelt crossings and shaded trail options.', 'Medium shard with softer terrain and nature rewards.']],
            'hout-bay-hollow' => ['real_name' => 'Hout Bay, Llandudno, Hangberg, Imizamo Yethu', 'facts' => ['Valley routes, harbour roads, and steep approaches.', 'Hard shard designed for special quests and scenic flex posts.']],
            'cape-flats-forge' => ['real_name' => 'Athlone, Lansdowne, Crawford, Hanover Park, Gugulethu', 'facts' => ['Wide horizons and honest distance-building roads.', 'Progression favors consistency and community route chains.']],
            'airport-ring' => ['real_name' => 'Cape Town International Airport, Belhar, Delft, Bonteheuwel, Langa', 'facts' => ['Wind corridors and gateway roads.', 'Easy shard with speed-flavored route challenges.']],
            'khayelitsha-dawn' => ['real_name' => 'Khayelitsha, Mitchells Plain, Philippi, Mfuleni, Blue Downs', 'facts' => ['Dense streets and early-start community energy.', 'Medium shard with social challenges and discovery rewards.']],
            'strandfontein-dunes' => ['real_name' => 'Strandfontein, Grassy Park, Lotus River, Pelican Park, Zeekoevlei', 'facts' => ['Coastal grind and dune air.', 'Medium quests reward pacing through exposed routes.']],
            'deep-south-wilds' => ['real_name' => 'Noordhoek, Kommetjie, Scarborough, Glencairn, Simon’s Town', 'facts' => ['Wild southern expansion territory with destination-run energy.', 'Hard shard built for long-route lore and rare rewards.']],
        ];

        foreach ($lore as $key => $data) {
            DB::table('regions')->where('key', $key)->update([
                'real_name' => $data['real_name'],
                'facts' => json_encode($data['facts']),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trail_notifications');

        Schema::table('regions', function (Blueprint $table) {
            $table->dropColumn(['real_name', 'facts']);
        });
    }
};
