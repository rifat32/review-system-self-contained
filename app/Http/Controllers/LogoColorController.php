<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Illuminate\Http\Request;
use League\ColorExtractor\Color;
use League\ColorExtractor\ColorExtractor;
use League\ColorExtractor\Palette;
use Symfony\Component\HttpFoundation\Response;
use OpenApi\Annotations as OA;

class LogoColorController extends Controller
{
    /**
     * @OA\Post(
     *      path="/logo/extract-colors",
     *      operationId="extractLogoColors",
     *      tags={"logo"},
     *      summary="Extract dominant colors from a logo and save it",
     *      description="Extracts colors, saves the logo, and updates the business header image.",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="multipart/form-data",
     *              @OA\Schema(
     *                  required={"logo", "business_id"},
     *                  @OA\Property(
     *                      property="logo",
     *                      description="Logo image file (max 4MB)",
     *                      type="string",
     *                      format="binary"
     *                  ),
     *                  @OA\Property(
     *                      property="business_id",
     *                      description="ID of the business",
     *                      type="integer"
     *                  )
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Colors extracted and logo saved successfully"),
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="primary", type="string", example="#0F172A"),
     *                  @OA\Property(property="secondary", type="string", example="#2563EB"),
     *                  @OA\Property(
     *                      property="accents",
     *                      type="array",
     *                      @OA\Items(type="string", example="#22C55E")
     *                  ),
     *                  @OA\Property(property="logo_url", type="string", example="https://example.com/img/logo/1234567890.png")
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Validation error"
     *      )
     * )
     */
    public function extract(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|max:4096', // Max 4MB
            'business_id' => 'required|exists:businesses,id'
        ]);

        try {
            $businessId = $request->input('business_id');
            $file = $request->file('logo');

            // 1. Save the logo
            $imageName = time() . '.' . $file->extension();
            $file->move(public_path('img/logo'), $imageName);
            $relativePath = "img/logo/" . $imageName;
            $fullUrl = asset($relativePath);

            // 2. Update Business
            $business = Business::find($businessId);
            if ($business) {
                // Assuming 'header_image' is the field for logo/header
                $business->header_image = '/' . $relativePath;
                $business->save();
            }

            // 3. Resize image for performance enhancement (Color Extraction)
            // Create image resource from the SAVED file to ensure we use valid path
            $imageContent = file_get_contents(public_path($relativePath));
            $image = imagecreatefromstring($imageContent);

            if (!$image) {
                return response()->json(['message' => 'Invalid image file'], 422);
            }

            // Scale image to max 300x300 while maintaining aspect ratio
            $scaledImage = imagescale($image, 300, -1);

            $palette = Palette::fromGD($scaledImage);
            $extractor = new ColorExtractor($palette);

            // Extract top 6 colors to give us options
            $colors = $extractor->extract(6);

            $filtered = collect($colors)
                ->map(fn ($color) => $this->intToHex($color))
                ->filter(fn ($hex) => $this->isUsableColor($hex))
                ->values();

            // Clean up GD resources
            imagedestroy($image);
            imagedestroy($scaledImage);

            return response()->json([
                "success" => true,
                "message" => "Colors extracted and logo saved successfully",
                "data" => [
                    'primary' => $filtered[0] ?? null,
                    'secondary' => $filtered[1] ?? null,
                    'accents' => $filtered->slice(2)->values(),
                    'logo_url' => $fullUrl
                ]
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            throw $e;
        }
    }

    private function intToHex(int $color): string
    {
        return Color::fromIntToHex($color);
    }

    private function isUsableColor(string $hex): bool
    {
        // Remove hash if present
        $hex = ltrim($hex, '#');

        // Parse hex
        if (strlen($hex) == 3) {
            $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }

        // Calculate brightness (perceived)
        // Formula: (R * 299 + G * 587 + B * 114) / 1000
        $brightness = ($r * 299 + $g * 587 + $b * 114) / 1000;

        // Remove near-white & near-black
        // Brightness range is 0-255
        // < 35 is very dark
        // > 245 is very bright
        return $brightness > 35 && $brightness < 245;
    }
}
