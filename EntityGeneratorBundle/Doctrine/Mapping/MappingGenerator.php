<?php

namespace IvozDevTools\EntityGeneratorBundle\Doctrine\Mapping;

use SimpleXMLElement;
use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

final class MappingGenerator extends Generator
{

    /**
     * @param array<array-key, array<string,string>> $mappings
     */
    public function __construct(
        private FileManager $fileManager,
        private array $mappings
    ) {
    }

    public function readXmlFile(string $filePath): ?\SimpleXMLElement
    {
        if (!file_exists($filePath)) {
            return null;
        }

        return simplexml_load_file($filePath);
    }

    /**
     * @return array<string, string>
     */
    public function getMappingsPath(): array
    {
        return $this->mappings['paths'];
    }

    /**
     * @return array<string, string>
     */
    public function getAliasMappingPaths(): array
    {
        return $this->mappings['aliasMap'];
    }

    public function generateMappingClass(string $mappingName, string $name): void
    {
        $outputPath = $this->mappings['paths'][$mappingName];

        if (!$outputPath) {
            throw new RuntimeCommandException('Output path not found');
        }

        $mappingFile = sprintf(
            '%s/%s.%s.orm.xml',
            $outputPath,
            $name,
            $name
        );
        $mappingAbstractFile = sprintf(
            '%s/%s.%sAbstract.orm.xml',
            $outputPath,
            $name,
            $name
        );

        $file = $this->readXmlFile($mappingFile);
        $fileAbstract = $this->readXmlFile($mappingAbstractFile);

        if (!$file) {
            $xmlString =
                $this->getXmlHeader() .
                $this->getXmlDoctrineEntityBody(
                    $name,
                    $mappingName
                );
            $this->generateXmlFromString(
                $xmlString,
                $mappingFile
            );
        }

        if (!$fileAbstract) {
            $xmlMappedSuperClassString =
                $this->getXmlHeader() .
                $this->getXmlDoctrineMappedSuperclassBody(
                    $name,
                    $mappingName
                );
            $this->generateXmlFromString(
                $xmlMappedSuperClassString,
                $mappingAbstractFile
            );
        }
    }

    private function getXmlHeader(): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>';
    }

    private function getXmlDoctrineEntityBody(
        string $entityName,
        string $mappingName
    ): string {
        $domainMappingAlias = $this->mappings['aliasMap'][$mappingName];
        $splitedMappingAlias = substr($domainMappingAlias, 0, strpos($domainMappingAlias, $mappingName));
        $xmlBody = '<doctrine-mapping xmlns="http://doctrine-project.org/schemas/orm/doctrine-mapping" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://doctrine-project.org/schemas/orm/doctrine-mapping https://www.doctrine-project.org/schemas/orm/doctrine-mapping.xsd">
    <entity
            repository-class="%s%s\Infrastructure\Persistence\Doctrine\%sDoctrineRepository"
            name="%s\%s\%s"
            table="%ss"
            change-tracking-policy="DEFERRED_EXPLICIT"
    >
        <id name="id" type="integer" column="id">
        <generator strategy="IDENTITY"/>
        <options>
            <option name="unsigned">1</option>
        </options>
        </id>
    </entity>
</doctrine-mapping>';

        return sprintf(
            $xmlBody,
            $splitedMappingAlias,
            $mappingName,
            $entityName,
            $domainMappingAlias,
            $entityName,
            $entityName,
            $entityName
        );
    }

    private function getXmlDoctrineMappedSuperclassBody(
        string $entityName,
        string $mappingName,
    ): string {

        $domainMappingAlias = $this->mappings['aliasMap'][$mappingName];

        $xmlBody = '<doctrine-mapping xmlns="http://doctrine-project.org/schemas/orm/doctrine-mapping" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://doctrine-project.org/schemas/orm/doctrine-mapping https://www.doctrine-project.org/schemas/orm/doctrine-mapping.xsd">
    <mapped-superclass name="%s\%s\%sAbstract">
    </mapped-superclass>
</doctrine-mapping>';

        return sprintf(
            $xmlBody,
            $domainMappingAlias,
            $entityName,
            $entityName
        );
    }

    private function generateXmlFromString(string $xmlString, string $mappingFile): void
    {
        $xml = new \SimpleXMLElement($xmlString);
        try {
            $xmlString = $xml->asXML();
            $this->fileManager->dumpFile($mappingFile, $xmlString);
        } catch (IOExceptionInterface $exception) {
            throw new \RuntimeException("Error writing XML file: " . $exception->getMessage());
        }
    }

    public function generateXml(SimpleXMLElement $xml, string $mappingFile): void
    {
        try {
            $dom = new \DOMDocument('1.0');
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;
            $dom->loadXML($xml->asXML());

            $xmlString = $dom->saveXML();
            $this->fileManager->dumpFile($mappingFile, $xmlString);
        } catch (IOExceptionInterface $exception) {
            throw new \RuntimeException("Error writing XML file: " . $exception->getMessage());
        }
    }
}
