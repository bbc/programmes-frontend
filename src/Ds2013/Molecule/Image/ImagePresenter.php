<?php
declare(strict_types = 1);
namespace App\Ds2013\Molecule\Image;

use App\Ds2013\InvalidOptionException;
use App\Ds2013\Presenter;
use BBC\ProgrammesPagesService\Domain\Entity\Image;
use InvalidArgumentException;

class ImagePresenter extends Presenter
{
    /** @inheritdoc */
    protected $options = [
        'is_lazy_loaded' => true,
        'srcsets' => [320, 480, 640, 768, 896, 1008],
        'ratio' => null,
        'alt' => '',
        'src_width' => null,
    ];

    /** @var Image */
    private $image;

    /** @var string */
    private $sizes;

    /**
     * ImagePresenter constructor.
     * @param Image $image
     * @param array|string $sizes
     * @param array $options
     */
    public function __construct(
        Image $image,
        $sizes,
        array $options = []
    ) {
        parent::__construct($options);

        if ((!is_string($sizes) && !is_array($sizes)) ||
            (is_array($sizes) && (!empty($sizes) && array_values($sizes) === $sizes))
        ) {
            throw new InvalidArgumentException("Argument 'sizes' must be either an empty or associative array, or a string");
        }

        $this->image = $image;
        $this->sizes = $this->buildSizes($sizes);
    }

    public function getSizes(): string
    {
        return $this->sizes;
    }

    public function getSrc(): string
    {
        if ($this->getOption('src_width')) {
            return $this->buildSrcUrl($this->getOption('src_width'));
        }

        return $this->buildSrcUrl($this->getOption('srcsets')[0]);
    }

    public function getSrcsets(): string
    {
        $srcsets = [];

        foreach ($this->getOption('srcsets') as $srcset) {
            $srcsets[] = $this->buildSrcUrl($srcset) . ' ' . $srcset . 'w';
        }

        return implode(', ', $srcsets);
    }

    protected function validateOptions(array $options): void
    {
        parent::validateOptions($options);

        if (!is_bool($options['is_lazy_loaded'])) {
            throw new InvalidOptionException("Option 'is_lazy_loaded' must be a boolean");
        }

        if (!is_array($options['srcsets'])) {
            throw new InvalidOptionException("Option 'srcsets' must be an array");
        }

        foreach ($options['srcsets'] as $srcset) {
            if (!is_numeric($srcset)) {
                throw new InvalidOptionException("Every 'srcsets' element must be numeric");
            }
        }

        if (!is_numeric($options['ratio']) && !is_null($options['ratio'])) {
            throw new InvalidOptionException("Option 'ratio' must be numeric or null");
        }

        if (!is_string($options['alt'])) {
            throw new InvalidOptionException("Option 'alt' must be a string");
        }

        if (!is_numeric($options['src_width']) && !is_null($options['src_width'])) {
            throw new InvalidOptionException("Option 'src_width' must be numeric or null");
        }

        if (!$options['src_width'] && !$options['srcsets']) {
            throw new InvalidOptionException("At least one of these options must be specified: 'src_width' or 'srcsets'");
        }
    }

    /**
     * @param string|int[] $sizes
     * @return string
     */
    private function buildSizes($sizes): string
    {
        if (is_string($sizes)) {
            return $sizes;
        }

        // Sizes must be ordered by largest first as we use min-width
        krsort($sizes, SORT_NUMERIC);

        $parts = [];

        foreach ($sizes as $width => $fraction) {
            $width = ($width / 16);

            if (!is_string($fraction)) {
                // Convert to percentage and append the 'vw'
                $fraction = ($fraction * 100);
                $fraction .= 'vw';
            }

            $parts[] = '(min-width: ' . $width . 'em) ' . $fraction;
        }

        // add the final 100vw in case nothing matched
        $parts[] = '100vw';

        return implode(', ', $parts);
    }

    private function buildSrcUrl(?int $width): string
    {
        if ($this->getOption('ratio')) {
            return $this->image->getUrl($width, $width / $this->getOption('ratio'));
        }

        return $this->image->getUrl($width);
    }
}