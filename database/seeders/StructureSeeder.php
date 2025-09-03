<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Structure;
use App\Models\Room;

class StructureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create main structures
        $structures = [
            [
                'name' => 'Main Breeding Barn',
                'code' => 'MBB',
                'description' => 'Primary facility for breeding stock management',
                'capacity' => 20,
                'status' => 'active',
                'rooms' => [
                    ['name' => 'Boar Pen 1', 'code' => 'BP01', 'type' => 'general', 'capacity' => 2],
                    ['name' => 'Boar Pen 2', 'code' => 'BP02', 'type' => 'general', 'capacity' => 2],
                    ['name' => 'Sow Pen 1', 'code' => 'SP01', 'type' => 'maternity', 'capacity' => 4],
                    ['name' => 'Sow Pen 2', 'code' => 'SP02', 'type' => 'maternity', 'capacity' => 4],
                    ['name' => 'Gestation Room', 'code' => 'GR01', 'type' => 'gestation', 'capacity' => 8],
                ]
            ],
            [
                'name' => 'Fattening Unit',
                'code' => 'FU',
                'description' => 'Facility for growing and finishing pigs',
                'capacity' => 30,
                'status' => 'active',
                'rooms' => [
                    ['name' => 'Grower Room 1', 'code' => 'GR01', 'type' => 'finisher', 'capacity' => 10],
                    ['name' => 'Grower Room 2', 'code' => 'GR02', 'type' => 'finisher', 'capacity' => 10],
                    ['name' => 'Finisher Room 1', 'code' => 'FR01', 'type' => 'finisher', 'capacity' => 10],
                ]
            ],
            [
                'name' => 'Weaning Facility',
                'code' => 'WF',
                'description' => 'Specialized facility for weaning piglets',
                'capacity' => 15,
                'status' => 'active',
                'rooms' => [
                    ['name' => 'Weaning Room 1', 'code' => 'WR01', 'type' => 'weaning', 'capacity' => 5],
                    ['name' => 'Weaning Room 2', 'code' => 'WR02', 'type' => 'weaning', 'capacity' => 5],
                    ['name' => 'Nursery Room', 'code' => 'NR01', 'type' => 'weaning', 'capacity' => 5],
                ]
            ],
            [
                'name' => 'Quarantine Building',
                'code' => 'QB',
                'description' => 'Isolation facility for new or sick animals',
                'capacity' => 5,
                'status' => 'active',
                'rooms' => [
                    ['name' => 'Quarantine Room 1', 'code' => 'QR01', 'type' => 'quarantine', 'capacity' => 3],
                    ['name' => 'Quarantine Room 2', 'code' => 'QR02', 'type' => 'quarantine', 'capacity' => 2],
                ]
            ]
        ];

        foreach ($structures as $structureData) {
            $rooms = $structureData['rooms'];
            unset($structureData['rooms']);
            
            $structure = Structure::updateOrCreate(
                ['code' => $structureData['code']],
                $structureData
            );
            
            foreach ($rooms as $roomData) {
                $roomData['structure_id'] = $structure->id;
                Room::updateOrCreate(
                    ['structure_id' => $structure->id, 'code' => $roomData['code']],
                    $roomData
                );
            }
        }
    }
}
