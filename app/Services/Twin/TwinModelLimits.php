<?php

namespace App\Services\Twin;

/**
 * The hard limits a 3D model file must stay within (OD-119), from config/twin.php. CLIENT_INPUTS_REQUIRED
 * C-6.5 states them for the 3D-modelling supplier.
 */
final readonly class TwinModelLimits
{
    public function __construct(
        public int $bytes,
        public int $triangles,
        public int $descriptionBytes,
        public int $nodes,
        public int $drawCalls,
        public int $materials,
        public int $textures,
        public int $textureSide,
        public int $texturePixels,
        public int $geometryBytes,
        public float $smallestMetres,
        public float $largestMetres,
        public float $checkSeconds,
    ) {}

    /**
     * @param  array{bytes: int, triangles: int, description_bytes: int, nodes: int, draw_calls: int, materials: int, textures: int, texture_side: int, texture_pixels: int, geometry_bytes: int, smallest_metres: int|float, largest_metres: int|float, check_seconds: int|float}  $limits  the `twin.limits` configuration
     */
    public static function fromConfig(array $limits): self
    {
        return new self(
            bytes: (int) $limits['bytes'],
            triangles: (int) $limits['triangles'],
            descriptionBytes: (int) $limits['description_bytes'],
            nodes: (int) $limits['nodes'],
            drawCalls: (int) $limits['draw_calls'],
            materials: (int) $limits['materials'],
            textures: (int) $limits['textures'],
            textureSide: (int) $limits['texture_side'],
            texturePixels: (int) $limits['texture_pixels'],
            geometryBytes: (int) $limits['geometry_bytes'],
            smallestMetres: (float) $limits['smallest_metres'],
            largestMetres: (float) $limits['largest_metres'],
            checkSeconds: (float) $limits['check_seconds'],
        );
    }

    /**
     * The limits as configured, kept with each model's check report so it shows what the file was held to.
     *
     * @return array{bytes: int, triangles: int, description_bytes: int, nodes: int, draw_calls: int, materials: int, textures: int, texture_side: int, texture_pixels: int, geometry_bytes: int, smallest_metres: float, largest_metres: float, check_seconds: float}
     */
    public function toArray(): array
    {
        return [
            'bytes' => $this->bytes,
            'triangles' => $this->triangles,
            'description_bytes' => $this->descriptionBytes,
            'nodes' => $this->nodes,
            'draw_calls' => $this->drawCalls,
            'materials' => $this->materials,
            'textures' => $this->textures,
            'texture_side' => $this->textureSide,
            'texture_pixels' => $this->texturePixels,
            'geometry_bytes' => $this->geometryBytes,
            'smallest_metres' => $this->smallestMetres,
            'largest_metres' => $this->largestMetres,
            'check_seconds' => $this->checkSeconds,
        ];
    }
}
