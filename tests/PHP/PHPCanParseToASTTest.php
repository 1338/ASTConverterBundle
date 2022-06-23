<?php

namespace MVDS\ASTConverterBundle\Tests;

use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\UseUse;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

class PHPCanParseToASTTest extends TestCase
{

    private string $phpTemplate;

    /**
     * @var Parser
     */
    private Parser $parser;

    private array $parsed;


    protected function setUp(): void
    {
        $this->parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
        $this->phpTemplate = file_get_contents(dirname(__FILE__) . "/../../src/Mock/Entity/User.php");
        $this->parsed = $this->parser->parse($this->phpTemplate);
    }


    public function testCanParse(): void
    {
        $this->assertArrayHasKey(0, $this->parsed, 'Parsed PHP should have 0 index');
    }

    public function testCanGetParsedClassNamespace(): void
    {
        $namespace = $this->parsed[0];

        // Make sure namespace is actually a parsed namespace
        $this->assertInstanceOf(
            Namespace_::class,
            $namespace,
            "PHPParser can't parse namespace correctly"
        );
    }

    public function testCanGetClassNamespaceAST(): void
    {
        /** @var Namespace_ $namespace */
        $namespace = $this->parsed[0];

        $this->assertObjectHasAttribute('name', $namespace, 'Namespace should have a name');

        $namespaceNameObject = $namespace->name;

        $this->assertSame(
            ['MVDS', 'AstConverterBundle', 'Mock', 'Entity'],
            $namespaceNameObject->parts,
            'We should be able to extract namespace parts'
        );
    }

    public function testCanGetClassUseStatements(): void
    {
        /** @var Namespace_ $namespace */
        $namespace = $this->parsed[0];

        $this->assertArrayHasKey(0, $namespace->stmts, 'Namespace should have statements');

        $this->assertInstanceOf(
            Use_::class,
            $namespace->stmts[0],
            'First statement should be a use statement'
        );


        /** @var Use_ $useStatement */
        $useStatement = $namespace->stmts[0];

        $this->assertArrayHasKey(0, $useStatement->uses, 'Use statement should have uses');

        $this->assertInstanceOf(
            UseUse::class,
            $useStatement->uses[0],
            'Use statement should have an use object'
        );

        $useUse = $useStatement->uses[0];

        $this->assertSame(
            ['Doctrine', 'ORM', 'Mapping'],
            $useUse->name->parts,
            'We should be able to extract use namespace parts'
        );

        $this->assertSame('ORM', $useUse->alias->name, 'We should be able to extract use alias');
    }

    public function testCanGetClass(): void
    {
        /** @var Namespace_ $namespace */
        $namespace = $this->parsed[0];

        $this->assertInstanceOf(
            Class_::class,
            $namespace->stmts[4],
            'Namespace object should contain class object'
        );


        /** @var Class_ $class */
        $class = $namespace->stmts[4];


        $this->assertSame('User', $class->name->name, "Should be able to extract class name");
    }

    public function testCanGetClassPropertyORM(): void
    {
        /** @var Class_ $class */
        $class = $this->parsed[0]->stmts[4];

        $classProperties = $class->getProperties();

        $this->assertNotEmpty($classProperties, 'Class should have properties');

        $property = $classProperties[0];

        $this->assertSame('id', $property->props[0]->name->name, 'Class property name should be id');

        $this->assertTrue($property->isPrivate(), 'Class property id is private');

        $ormData = [
            'name' => $property->props[0]->name->__toString()
        ];

        foreach ($property->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if($attr->name->__toString() === 'ORM\\Column') {
                    foreach ($attr->args as $arg) {
                        if($arg->name->__toString() === 'type') {
                            $ormData['type'] = $arg->value->value;
                        }
                    }
                }
            }
        }

        $this->assertSame(['name' => 'id', 'type' => 'integer'], $ormData);
    }
}