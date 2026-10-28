<?php

namespace App\Services\Twin;

/**
 * Reads how many triangles a Draco-compressed mesh (KHR_draco_mesh_compression) really holds. The viewer
 * draws the decoded mesh whatever the file's description declares, so this count, not the declared one,
 * is held to the triangle limit. Only the header, any metadata and the start of the connectivity data
 * are read; nothing is decompressed. Draco bitstream versions 2.0 to 2.2 (current encoders write 2.2),
 * sequential and Edgebreaker encoding, as in the Draco decoder's MeshSequentialDecoder and
 * MeshEdgebreakerDecoderImpl.
 */
class DracoStream
{
    private const int TRIANGULAR_MESH = 1;

    private const int SEQUENTIAL_ENCODING = 0;

    private const int EDGEBREAKER_ENCODING = 1;

    private const int METADATA_FLAG = 0x8000;

    /** Nesting allowed in the metadata before the stream is treated as damaged. */
    private const int METADATA_DEPTH = 32;

    /**
     * @return array{version: string, faces: int}|null null when it is not a Draco mesh in a version this reads, or is damaged
     */
    public function inspect(string $bytes): ?array
    {
        if (strlen($bytes) < 11 || ! str_starts_with($bytes, 'DRACO')) {
            return null;
        }

        [$major, $minor, $type, $method] = array_map('ord', str_split(substr($bytes, 5, 4)));
        $flags = unpack('v', $bytes, 9)[1];
        $version = $major * 10 + $minor;

        if ($major !== 2 || $minor > 2 || $type !== self::TRIANGULAR_MESH || ! in_array($method, [self::SEQUENTIAL_ENCODING, self::EDGEBREAKER_ENCODING], true)) {
            return null;
        }

        $offset = 11;

        if (($flags & self::METADATA_FLAG) !== 0 && ! $this->skipGeometryMetadata($bytes, $offset)) {
            return null;
        }

        $faces = $method === self::SEQUENTIAL_ENCODING
            ? ($version < 22 ? $this->uint32($bytes, $offset) : $this->varint($bytes, $offset))
            : $this->edgebreakerFaces($bytes, $offset, $version);

        return $faces !== null && $faces <= intdiv(0xFFFFFFFF, 3) ? ['version' => "{$major}.{$minor}", 'faces' => $faces] : null;
    }

    /**
     * Traversal type, then (before 2.2) the number of new vertices, the number of encoded vertices and the
     * number of faces.
     */
    private function edgebreakerFaces(string $bytes, int $offset, int $version): ?int
    {
        $offset++;

        if ($version < 22 && $this->varint($bytes, $offset) === null) {
            return null;
        }

        $encodedVertices = $this->varint($bytes, $offset);

        return $encodedVertices === null ? null : $this->varint($bytes, $offset);
    }

    /**
     * Attribute metadata (each with its attribute's id), then the file's metadata.
     */
    private function skipGeometryMetadata(string $bytes, int &$offset): bool
    {
        $attributes = $this->varint($bytes, $offset);

        for ($index = 0; $attributes !== null && $index < $attributes; $index++) {
            if ($this->varint($bytes, $offset) === null || ! $this->skipMetadata($bytes, $offset, 0)) {
                return false;
            }
        }

        return $attributes !== null && $this->skipMetadata($bytes, $offset, 0);
    }

    /**
     * Entries (name, size, value), then named sub-metadata, each written in full where it is named.
     */
    private function skipMetadata(string $bytes, int &$offset, int $depth): bool
    {
        $entries = $this->varint($bytes, $offset);

        if ($entries === null || $depth > self::METADATA_DEPTH) {
            return false;
        }

        for ($index = 0; $index < $entries; $index++) {
            if (! $this->skipName($bytes, $offset)) {
                return false;
            }

            $size = $this->varint($bytes, $offset);
            $offset += (int) $size;

            if (! $size || $offset > strlen($bytes)) {
                return false;
            }
        }

        $children = $this->varint($bytes, $offset);

        for ($index = 0; $children !== null && $index < $children; $index++) {
            if (! $this->skipName($bytes, $offset) || ! $this->skipMetadata($bytes, $offset, $depth + 1)) {
                return false;
            }
        }

        return $children !== null;
    }

    private function skipName(string $bytes, int &$offset): bool
    {
        if ($offset >= strlen($bytes)) {
            return false;
        }

        $offset += 1 + ord($bytes[$offset]);

        return $offset <= strlen($bytes);
    }

    private function uint32(string $bytes, int &$offset): ?int
    {
        if ($offset + 4 > strlen($bytes)) {
            return null;
        }

        $offset += 4;

        return unpack('V', $bytes, $offset - 4)[1];
    }

    /**
     * An unsigned LEB128 number of at most 32 bits, as Draco's DecodeVarint reads it.
     */
    private function varint(string $bytes, int &$offset): ?int
    {
        $value = 0;

        for ($shift = 0; $shift < 35; $shift += 7) {
            if ($offset >= strlen($bytes)) {
                return null;
            }

            $byte = ord($bytes[$offset++]);
            $value |= ($byte & 0x7F) << $shift;

            if (($byte & 0x80) === 0) {
                return $value <= 0xFFFFFFFF ? $value : null;
            }
        }

        return null;
    }
}
