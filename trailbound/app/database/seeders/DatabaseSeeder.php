<?php

namespace Database\Seeders;

use App\Models\Region;
use App\Models\Task;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $poly = fn (array $points) => ["type" => "Polygon", "coordinates" => [$points]];

        $regions = [
            [
                "key" => "city-bowl",
                "name" => "City Bowl",
                "biome" => "urban ember",
                "summary" => "The starter basin: signal towers, stair streets, old stone, and short punchy climbs.",
                "difficulty" => "starter",
                "map_x" => 46, "map_y" => 45, "sort_order" => 1,
                "start_keywords" => ["city bowl","gardens","tamboerskloof","oranjezicht","vredehoek","cbd","bo-kaap","zonnebloem","woodstock","salt river","observatory","de waterkant"],
                "polygon" => $poly([[18.380,-33.895],[18.430,-33.895],[18.430,-33.950],[18.380,-33.950],[18.380,-33.895]]),
            ],
            [
                "key" => "atlantic-edge",
                "name" => "Atlantic Edge",
                "biome" => "saltwind coast",
                "summary" => "Sea air, promenade tempo runs, and bright cliffside routes from Sea Point to Camps Bay.",
                "difficulty" => "easy",
                "map_x" => 28, "map_y" => 52, "sort_order" => 2,
                "start_keywords" => ["sea point","green point","camps bay","bantry bay","clifton","waterfront","mouille point","three anchor bay","fresnaye","bakoven"],
                "polygon" => $poly([[18.300,-33.895],[18.380,-33.895],[18.380,-34.020],[18.300,-34.020],[18.300,-33.895]]),
            ],
            [
                "key" => "table-crown",
                "name" => "Table Crown",
                "biome" => "fynbos highland",
                "summary" => "The mountain wall. Fynbos, stone steps, steep reveals, and rare high-altitude quests.",
                "difficulty" => "hard",
                "map_x" => 52, "map_y" => 33, "sort_order" => 3,
                "start_keywords" => ["table mountain","devils peak","constantia nek","newlands forest","kirstenbosch","bishops court"],
                "polygon" => $poly([[18.380,-33.950],[18.450,-33.950],[18.450,-34.020],[18.380,-34.020],[18.380,-33.950]]),
            ],
            [
                "key" => "southern-vines",
                "name" => "Southern Vines",
                "biome" => "vineyard vale",
                "summary" => "Leafy roads, vineyard loops, and longer endurance chains through the southern suburbs.",
                "difficulty" => "medium",
                "map_x" => 59, "map_y" => 64, "sort_order" => 4,
                "start_keywords" => ["constantia","wynberg","plumstead","tokai","claremont","kenilworth","rondebosch","newlands","mowbray","rosebank","bergvliet","diep river","meadowridge"],
                "polygon" => $poly([[18.450,-34.020],[18.540,-34.020],[18.540,-34.080],[18.450,-34.080],[18.450,-34.020]]),
            ],
            [
                "key" => "false-bay",
                "name" => "False Bay",
                "biome" => "tidal reef",
                "summary" => "Wind, warmer water, tidal roads, and discovery tasks down Muizenberg and Kalk Bay.",
                "difficulty" => "medium",
                "map_x" => 72, "map_y" => 79, "sort_order" => 5,
                "start_keywords" => ["muizenberg","kalk bay","fish hoek","simons town","st james","lakeside","marina da gama","glencairn","scarborough","kommetjie","noordhoek","sun valley"],
                "polygon" => $poly([[18.480,-34.080],[18.620,-34.080],[18.620,-34.180],[18.480,-34.180],[18.480,-34.080]]),
            ],
            [
                "key" => "northern-grid",
                "name" => "Northern Grid",
                "biome" => "granite flats",
                "summary" => "Wide roads, business parks, hidden wetlands, and pragmatic distance-building routes.",
                "difficulty" => "easy",
                "map_x" => 68, "map_y" => 39, "sort_order" => 6,
                "start_keywords" => ["bellville","durbanville","parow","century city","milnerton","goodwood","bothasig","edgemead","monte vista","platte kloof","brackenfell","kuils river"],
                "polygon" => $poly([[18.450,-33.860],[18.750,-33.860],[18.750,-33.920],[18.450,-33.920],[18.450,-33.860]]),
            ],
            [
                "key" => "west-coast-watch",
                "name" => "West Coast Watch",
                "biome" => "dune sentinel",
                "summary" => "Salt haze, long beachfront straights, and Table Mountain framed like a boss arena.",
                "difficulty" => "easy",
                "map_x" => 39, "map_y" => 28, "sort_order" => 7,
                "start_keywords" => ["table view","blouberg","bloubergstrand","parklands","sunningdale","big bay","melkbosstrand"],
                "polygon" => $poly([[18.300,-33.760],[18.450,-33.760],[18.450,-33.860],[18.300,-33.860],[18.300,-33.760]]),
            ],
            [
                "key" => "tygerberg-spine",
                "name" => "Tygerberg Spine",
                "biome" => "granite ridge",
                "summary" => "A northern ridge of fast road loops, office-park intervals, and hidden elevation.",
                "difficulty" => "medium",
                "map_x" => 64, "map_y" => 28, "sort_order" => 8,
                "start_keywords" => ["tygerberg","welgemoed","kenridge","plattekloof","panorama","bellville"],
                "polygon" => $poly([[18.450,-33.760],[18.580,-33.760],[18.580,-33.860],[18.450,-33.860],[18.450,-33.760]]),
            ],
            [
                "key" => "durbanville-crown",
                "name" => "Durbanville Crown",
                "biome" => "vine crown",
                "summary" => "Rolling northern vineyards where easy kilometres quietly become climbing legs.",
                "difficulty" => "medium",
                "map_x" => 77, "map_y" => 21, "sort_order" => 9,
                "start_keywords" => ["durbanville","vierlanden","sonstraal","uitzicht","goedemoed"],
                "polygon" => $poly([[18.580,-33.760],[18.750,-33.760],[18.750,-33.860],[18.580,-33.860],[18.580,-33.760]]),
            ],
            [
                "key" => "industrial-anvil",
                "name" => "Industrial Anvil",
                "biome" => "steel flats",
                "summary" => "Hard, practical roads through Epping, Maitland, Goodwood, and the city’s working engine.",
                "difficulty" => "easy",
                "map_x" => 58, "map_y" => 45, "sort_order" => 10,
                "start_keywords" => ["epping","maitland","goodwood","elsies river","bishop lavis","matroosfontein"],
                "polygon" => $poly([[18.430,-33.920],[18.560,-33.920],[18.560,-33.970],[18.430,-33.970],[18.430,-33.920]]),
            ],
            [
                "key" => "southern-line",
                "name" => "Southern Line",
                "biome" => "rail garden",
                "summary" => "Old rail towns, leafy avenues, school fields, and repeatable everyday route chains.",
                "difficulty" => "starter",
                "map_x" => 54, "map_y" => 58, "sort_order" => 11,
                "start_keywords" => ["mowbray","rosebank","rondebosch","claremont","kenilworth","harfield","wynberg"],
                "polygon" => $poly([[18.430,-33.970],[18.520,-33.970],[18.520,-34.020],[18.430,-34.020],[18.430,-33.970]]),
            ],
            [
                "key" => "constantia-greenbelt",
                "name" => "Constantia Greenbelt",
                "biome" => "oak vale",
                "summary" => "Soft trails, greenbelt crossings, vineyard air, and the feeling of running inside a secret.",
                "difficulty" => "medium",
                "map_x" => 48, "map_y" => 71, "sort_order" => 12,
                "start_keywords" => ["constantia","meadowridge","bergvliet","diep river","heathfield"],
                "polygon" => $poly([[18.380,-34.020],[18.450,-34.020],[18.450,-34.080],[18.380,-34.080],[18.380,-34.020]]),
            ],
            [
                "key" => "hout-bay-hollow",
                "name" => "Hout Bay Hollow",
                "biome" => "mist harbor",
                "summary" => "A tucked-away valley shard: harbour roads, forest climbs, and Chapman’s Peak pressure.",
                "difficulty" => "hard",
                "map_x" => 32, "map_y" => 72, "sort_order" => 13,
                "start_keywords" => ["hout bay","llandudno","im izamo yethu","hangberg"],
                "polygon" => $poly([[18.280,-34.020],[18.380,-34.020],[18.380,-34.100],[18.280,-34.100],[18.280,-34.020]]),
            ],
            [
                "key" => "cape-flats-forge",
                "name" => "Cape Flats Forge",
                "biome" => "sunbaked grid",
                "summary" => "Wide horizons, gritty distance-building roads, and endurance earned honestly.",
                "difficulty" => "medium",
                "map_x" => 66, "map_y" => 62, "sort_order" => 14,
                "start_keywords" => ["athlone","lansdowne","gatesville","crawford","hanover park","manenberg","guguletu"],
                "polygon" => $poly([[18.520,-33.970],[18.620,-33.970],[18.620,-34.050],[18.520,-34.050],[18.520,-33.970]]),
            ],
            [
                "key" => "airport-ring",
                "name" => "Airport Ring",
                "biome" => "runway storm",
                "summary" => "Wind corridors, service roads, and a strange sense of speed around the city’s gateway.",
                "difficulty" => "easy",
                "map_x" => 73, "map_y" => 53, "sort_order" => 15,
                "start_keywords" => ["airport","belhar","delft","bonteheuwel","langa","nyanga"],
                "polygon" => $poly([[18.560,-33.920],[18.750,-33.920],[18.750,-33.970],[18.560,-33.970],[18.560,-33.920]]),
            ],
            [
                "key" => "khayelitsha-dawn",
                "name" => "Khayelitsha Dawn",
                "biome" => "morning plain",
                "summary" => "Huge sky, dense streets, early-start energy, and community routes with momentum.",
                "difficulty" => "medium",
                "map_x" => 82, "map_y" => 67, "sort_order" => 16,
                "start_keywords" => ["khayelitsha","mitchells plain","philippi","mfuleni","blue downs","eastridge"],
                "polygon" => $poly([[18.620,-33.970],[18.750,-33.970],[18.750,-34.080],[18.620,-34.080],[18.620,-33.970]]),
            ],
            [
                "key" => "strandfontein-dunes",
                "name" => "Strandfontein Dunes",
                "biome" => "wind dune",
                "summary" => "Coastal grind, dune air, and long exposed efforts where pacing matters.",
                "difficulty" => "medium",
                "map_x" => 72, "map_y" => 84, "sort_order" => 17,
                "start_keywords" => ["strandfontein","grassy park","lotus river","pelican park","zeekoevlei"],
                "polygon" => $poly([[18.540,-34.050],[18.620,-34.050],[18.620,-34.080],[18.540,-34.080],[18.540,-34.050]]),
            ],
            [
                "key" => "deep-south-wilds",
                "name" => "Deep South Wilds",
                "biome" => "kelp wild",
                "summary" => "Noordhoek, Kommetjie, Simon’s Town and the wild southern routes that feel like an expansion pack.",
                "difficulty" => "hard",
                "map_x" => 56, "map_y" => 91, "sort_order" => 18,
                "start_keywords" => ["noordhoek","kommetjie","scarborough","simon","glencairn","sun valley","capri"],
                "polygon" => $poly([[18.280,-34.100],[18.480,-34.100],[18.480,-34.180],[18.280,-34.180],[18.280,-34.100]]),
            ],
        ];

        foreach ($regions as $data) {
            $region = Region::query()->updateOrCreate(["key" => $data["key"]], [
                "name" => $data["name"], "biome" => $data["biome"], "summary" => $data["summary"],
                "real_name" => collect($data["start_keywords"])->take(8)->map(fn ($place) => ucwords($place))->implode(", "),
                "facts" => [
                    $data["summary"],
                    "Trailbound quests in this shard use real Cape Town movement to reveal game progress.",
                ],
                "difficulty" => $data["difficulty"], "map_x" => $data["map_x"], "map_y" => $data["map_y"],
                "sort_order" => $data["sort_order"], "start_keywords" => $data["start_keywords"],
                "polygon" => $data["polygon"],
            ]);

            $taskSets = [
                ["First Footfall", "Log any run in this region to mark your arrival.", "Run 1km in-region", 1, 120],
                ["Trace the Boundary", "Build enough distance to reveal the next boundary marker.", "Complete a 3km effort", 3, 260],
                ["Biome Attunement", "Prove you can read the terrain and unlock this biome track.", "Complete a 5km effort", 5, 420],
            ];

            foreach ($taskSets as $order => [$title, $description, $rule, $target, $xp]) {
                Task::query()->updateOrCreate(["region_id" => $region->id, "unlock_order" => $order + 1], [
                    "title" => $title, "description" => $description, "unlock_rule" => $rule,
                    "target_value" => $target, "reward_xp" => $xp,
                ]);
            }
        }
    }
}
