<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'is_admin')) {
                $table->boolean('is_admin')->default(false)->after('password');
            }
        });

        $poly = fn (array $points) => json_encode(['type' => 'Polygon', 'coordinates' => [$points]], JSON_THROW_ON_ERROR);
        $regions = [
            'west-coast-watch' => [[18.300,-33.760],[18.450,-33.760],[18.450,-33.860],[18.300,-33.860],[18.300,-33.760]],
            'tygerberg-spine' => [[18.450,-33.760],[18.580,-33.760],[18.580,-33.860],[18.450,-33.860],[18.450,-33.760]],
            'durbanville-crown' => [[18.580,-33.760],[18.750,-33.760],[18.750,-33.860],[18.580,-33.860],[18.580,-33.760]],
            'northern-grid' => [[18.450,-33.860],[18.750,-33.860],[18.750,-33.920],[18.450,-33.920],[18.450,-33.860]],
            'atlantic-edge' => [[18.300,-33.890],[18.380,-33.890],[18.380,-34.020],[18.300,-34.020],[18.300,-33.890]],
            'city-bowl' => [[18.380,-33.890],[18.430,-33.890],[18.430,-33.950],[18.380,-33.950],[18.380,-33.890]],
            'industrial-anvil' => [[18.430,-33.920],[18.560,-33.920],[18.560,-33.970],[18.430,-33.970],[18.430,-33.920]],
            'airport-ring' => [[18.560,-33.920],[18.750,-33.920],[18.750,-33.970],[18.560,-33.970],[18.560,-33.920]],
            'table-crown' => [[18.380,-33.950],[18.430,-33.950],[18.430,-34.020],[18.380,-34.020],[18.380,-33.950]],
            'southern-line' => [[18.430,-33.970],[18.520,-33.970],[18.520,-34.020],[18.430,-34.020],[18.430,-33.970]],
            'cape-flats-forge' => [[18.520,-33.970],[18.620,-33.970],[18.620,-34.050],[18.520,-34.050],[18.520,-33.970]],
            'khayelitsha-dawn' => [[18.620,-33.970],[18.750,-33.970],[18.750,-34.080],[18.620,-34.080],[18.620,-33.970]],
            'hout-bay-hollow' => [[18.280,-34.020],[18.380,-34.020],[18.380,-34.100],[18.280,-34.100],[18.280,-34.020]],
            'constantia-greenbelt' => [[18.380,-34.020],[18.450,-34.020],[18.450,-34.080],[18.380,-34.080],[18.380,-34.020]],
            'southern-vines' => [[18.450,-34.020],[18.520,-34.020],[18.520,-34.080],[18.450,-34.080],[18.450,-34.020]],
            'strandfontein-dunes' => [[18.520,-34.050],[18.620,-34.050],[18.620,-34.080],[18.520,-34.080],[18.520,-34.050]],
            'false-bay' => [[18.480,-34.080],[18.620,-34.080],[18.620,-34.180],[18.480,-34.180],[18.480,-34.080]],
            'deep-south-wilds' => [[18.280,-34.100],[18.480,-34.100],[18.480,-34.180],[18.280,-34.180],[18.280,-34.100]],
        ];

        foreach ($regions as $key => $polygon) {
            DB::table('regions')->where('key', $key)->update(['polygon' => $poly($polygon)]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_admin')) {
                $table->dropColumn('is_admin');
            }
        });
    }
};
