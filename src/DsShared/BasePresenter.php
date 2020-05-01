<?php
declare(strict_types = 1);
namespace App\DsShared;

use App\Exception\InvalidOptionException;

/**
 * Base Class for a all design system Presenters
 */
abstract class BasePresenter
{
    /** @var array */
    protected static $presenterNameCache = [];

    /** @var array */
    protected static $templatePathCache = [];

    /** @var array */
    protected $options = [];

    /** @var string */
    protected $uniqueId;

    /**
     * Set this constant in your child class if you want to override the template file and presenter name used by that
     * class. Simply set this to the ::class name of the presenter whose template you wish to use, and it will pick that up
     */
    const TEMPLATE_PATH_CLASS_OVERRIDE = null;

    public function __construct(array $options = [])
    {
        $this->options = array_merge($this->options, $options);
        $this->validateOptions($this->options);
    }

    /**
     * Full path to the twig template the presenter class renders
     */
    public function getTemplatePath(): string
    {
        // set the class we want to use to generate the twig template path
        $class = (static::TEMPLATE_PATH_CLASS_OVERRIDE) ? static::TEMPLATE_PATH_CLASS_OVERRIDE : static::class;

        if (!isset(self::$templatePathCache[$class])) {
            $designSystem = $this->getDesignSystem();
            $classPath = str_replace('App\\Ds' . $designSystem . '\\', '', $class);

            $parts = implode('/', explode('\\', $classPath, -1));
            $pathPrefix = '@Ds' . $designSystem . '/' . $parts . ($parts ? '/' : '');

            self::$templatePathCache[$class] = $pathPrefix . $this->snakeCasePresenterName($class) . '.html.twig';
        }

        return self::$templatePathCache[$class];
    }

    /**
     * Get the base property that the twig template will be expecting
     * to find its variables under. Twig conventions say variable names should
     * use snake_case. So the "myExamplePresenter" presenter's variable name
     * would be "my_example"
     */
    public function getTemplateVariableName(): string
    {
        $class = (static::TEMPLATE_PATH_CLASS_OVERRIDE) ? static::TEMPLATE_PATH_CLASS_OVERRIDE : static::class;
        return $this->snakeCasePresenterName($class);
    }

    public function getOption($keyOption)
    {
        if (array_key_exists($keyOption, $this->options)) {
            return $this->options[$keyOption];
        }

        throw new InvalidOptionException(sprintf(
            'Called getOption with an invalid value. Expected one of %s but got "%s"',
            '"' . implode('", "', array_keys($this->options)) . '"',
            $keyOption
        ));
    }

    /**
     * Get or generate a unique ID. Once generated once the same one will be used
     * Only used for unique references in a single render
     */
    public function getUniqueId(): string
    {
        if (!$this->uniqueId) {
            $parts = explode('\\', static::class);
            $class = end($parts);
            $this->uniqueId = 'ds-' . strtolower($this->getDesignSystem()) . '-' . $class . '-' . mt_rand();
        }

        return $this->uniqueId;
    }

    /**
     * The name of the design system
     *
     * @return string
     */
    abstract protected function getDesignSystem(): string;

    /**
     * Validate options. Should be overridden.
     */
    protected function validateOptions(array $options): void
    {
    }

    /**
     * Calculates the presenter name for use in referencing the template and
     * the name of the variable to be passed to that template.
     * e.g. App\DsShared\Organism\ExampleThingPresenter becomes example_thing
     *
     * @param string $class
     * @return string
     */
    private function snakeCasePresenterName(string $class): string
    {
        if (!isset(self::$presenterNameCache[$class])) {
            $namespaceEnd = strrpos($class, '\\');
            $shortClassName = substr($class, $namespaceEnd + ($namespaceEnd ? 1: 0));

            // Trim the class name from the word 'Presenter'
            $presenterName = substr($shortClassName, 0, strpos($shortClassName, 'Presenter'));

            self::$presenterNameCache[$class] = strtolower(preg_replace(
                ["/([A-Z]+)/", "/_([A-Z]+)([A-Z][a-z])/"],
                ["_$1", "_$1_$2"],
                lcfirst($presenterName)
            ));
        }
        return self::$presenterNameCache[$class];
    }
}
