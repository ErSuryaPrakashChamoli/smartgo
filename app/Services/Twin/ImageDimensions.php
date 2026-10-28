<?php

namespace App\Services\Twin;

/**
 * The format and pixel size of an image embedded in a GLB file, read from its header without decoding
 * it: PNG, JPEG, WebP and KTX2, the formats the viewer can show. The format comes from the bytes, never
 * from a declared type or a file name.
 */
class ImageDimensions
{
    /** JPEG frame headers: every SOFn marker except DHT (C4), JPG (C8) and DAC (CC). */
    private const array JPEG_FRAME_MARKERS = [0xC0, 0xC1, 0xC2, 0xC3, 0xC5, 0xC6, 0xC7, 0xC9, 0xCA, 0xCB, 0xCD, 0xCE, 0xCF];

    /**
     * @return array{format: string, width: int, height: int}|null null when the image is not one of these formats or its header cannot be read
     */
    public function read(string $bytes): ?array
    {
        return match (true) {
            str_starts_with($bytes, "\x89PNG\r\n\x1a\n") => $this->png($bytes),
            str_starts_with($bytes, "\xFF\xD8") => $this->jpeg($bytes),
            str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP' => $this->webp($bytes),
            str_starts_with($bytes, "\xABKTX 20\xBB\r\n\x1A\n") => $this->ktx2($bytes),
            default => null,
        };
    }

    /**
     * @return array{format: string, width: int, height: int}|null
     */
    private function png(string $bytes): ?array
    {
        if (strlen($bytes) < 24 || substr($bytes, 12, 4) !== 'IHDR') {
            return null;
        }

        ['width' => $width, 'height' => $height] = unpack('Nwidth/Nheight', $bytes, 16);

        return $this->dimensions('png', $width, $height);
    }

    /**
     * Walks the marker segments to the first frame header.
     *
     * @return array{format: string, width: int, height: int}|null
     */
    private function jpeg(string $bytes): ?array
    {
        $length = strlen($bytes);
        $offset = 2;

        while ($offset + 4 <= $length) {
            if ($bytes[$offset] !== "\xFF") {
                return null;
            }

            $marker = ord($bytes[$offset + 1]);

            if ($marker === 0xFF) {
                $offset++;

                continue;
            }

            if ($marker === 0x01 || ($marker >= 0xD0 && $marker <= 0xD8)) {
                $offset += 2;

                continue;
            }

            if ($marker === 0xD9 || $marker === 0xDA) {
                return null;
            }

            if (in_array($marker, self::JPEG_FRAME_MARKERS, true)) {
                if ($offset + 9 > $length) {
                    return null;
                }

                ['height' => $height, 'width' => $width] = unpack('nheight/nwidth', $bytes, $offset + 5);

                return $this->dimensions('jpeg', $width, $height);
            }

            $segment = unpack('n', $bytes, $offset + 2)[1];

            if ($segment < 2) {
                return null;
            }

            $offset += 2 + $segment;
        }

        return null;
    }

    /**
     * Lossy (VP8), lossless (VP8L) and extended (VP8X) WebP.
     *
     * @return array{format: string, width: int, height: int}|null
     */
    private function webp(string $bytes): ?array
    {
        if (strlen($bytes) < 30) {
            return null;
        }

        return match (substr($bytes, 12, 4)) {
            'VP8 ' => substr($bytes, 23, 3) === "\x9d\x01\x2a"
                ? $this->dimensions('webp', unpack('v', $bytes, 26)[1] & 0x3FFF, unpack('v', $bytes, 28)[1] & 0x3FFF)
                : null,
            'VP8L' => $bytes[20] === "\x2f"
                ? $this->dimensions('webp', (unpack('V', $bytes, 21)[1] & 0x3FFF) + 1, ((unpack('V', $bytes, 21)[1] >> 14) & 0x3FFF) + 1)
                : null,
            'VP8X' => $this->dimensions('webp', unpack('V', substr($bytes, 24, 3)."\0")[1] + 1, unpack('V', substr($bytes, 27, 3)."\0")[1] + 1),
            default => null,
        };
    }

    /**
     * @return array{format: string, width: int, height: int}|null
     */
    private function ktx2(string $bytes): ?array
    {
        if (strlen($bytes) < 28) {
            return null;
        }

        ['width' => $width, 'height' => $height] = unpack('Vwidth/Vheight', $bytes, 20);

        return $this->dimensions('ktx2', $width, $height);
    }

    /**
     * @return array{format: string, width: int, height: int}|null
     */
    private function dimensions(string $format, int $width, int $height): ?array
    {
        return $width > 0 && $height > 0 ? ['format' => $format, 'width' => $width, 'height' => $height] : null;
    }
}
