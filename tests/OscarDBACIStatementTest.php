<?php

use OscarDB\ACI_PDO\ACIException;
use OscarDB\ACI_PDO\ACIStatement;
use Mockery as m;
use PHPUnit\Framework\TestCase;

include 'mocks/ACIMocks.php';
include 'mocks/ACIFunctions.php';

class OscarDBACIStatementTest extends TestCase
{
    protected function setUp(): void
    {
        if (! extension_loaded('aci')) {
            $this->markTestSkipped(
              'The oscar extension is not available.'
            );
        } else {
            global $ACIStatementStatus, $ACIExecuteStatus, $ACIFetchStatus, $ACIBindChangeStatus;

            $ACIStatementStatus = true;
            $ACIExecuteStatus = true;
            $ACIFetchStatus = true;
            $ACIBindChangeStatus = false;

            $this->aci = m::mock(new \TestACIStub('', null, null, [\PDO::ATTR_CASE => \PDO::CASE_LOWER]));
            $this->stmt = m::mock(new \TestACIStatementStub('aci statement', $this->aci, '', ['fake' => 'attribute']));

            //fake result sets for all the fetch calls
            $this->resultUpperArray = ['FNAME' => 'Test', 'LNAME' => 'Testerson', 'EMAIL' => 'tester@testing.com'];
            $this->resultUpperObject = (object) $this->resultUpperArray;
            $this->resultLowerArray = array_change_key_case($this->resultUpperArray, \CASE_LOWER);
            $this->resultLowerObject = (object) $this->resultLowerArray;

            $this->resultNumArray = [0 => 'Test', 1 => 'Testerson', 2 => 'tester@testing.com'];

            $this->resultBothUpperArray = [0 => 'Test', 1 => 'Testerson', 2 => 'tester@testing.com', 'FNAME' => 'Test', 'LNAME' => 'Testerson', 'EMAIL' => 'tester@testing.com'];
            $this->resultBothLowerArray = array_change_key_case($this->resultBothUpperArray, \CASE_LOWER);

            $this->resultAllUpperArray = [$this->resultUpperArray];
            $this->resultAllUpperObject = [$this->resultUpperObject];
            $this->resultAllLowerArray = [$this->resultLowerArray];
            $this->resultAllLowerObject = [$this->resultLowerObject];

            $this->resultAllNumArray = [$this->resultNumArray];

            $this->resultAllBothUpperArray = [$this->resultBothUpperArray];
            $this->resultAllBothLowerArray = [$this->resultBothLowerArray];
        }
    }

    public function tearDown(): void
    {
        m::close();
    }

    public function testConstructor()
    {
        $aci = new \TestACIStub();
        $ocistmt = new ACIStatement('aci statement', $aci);

        // use reflection to test values of protected properties
        $reflection = new \ReflectionClass($ocistmt);

        // stmt property
        $property = $reflection->getProperty('stmt');
        $property->setAccessible(true);
        $this->assertEquals('aci statement', $property->getValue($ocistmt));

        //conn property
        $property = $reflection->getProperty('conn');
        $property->setAccessible(true);
        $this->assertEquals($aci, $property->getValue($ocistmt));

        //attributes property
        $property = $reflection->getProperty('attributes');
        $property->setAccessible(true);
        $this->assertEquals([], $property->getValue($ocistmt));
    }

    public function testConstructorWithoutValidStatementPassignIn()
    {
        global $ACIStatementStatus;
        $ACIStatementStatus = false;
        $this->expectException(ACIException::class);
        $ocistmt = new ACIStatement('aci statement', new \TestACIStub());
    }

    public function testDestructor()
    {
        global $ACIStatementStatus;
        $ocistmt = new ACIStatement('aci statement', new \TestACIStub());
        unset($ocistmt);
        $this->assertFalse($ACIStatementStatus);
    }

    public function testBindColumnWithColumnName()
    {
        $stmt = new \TestACIStatementStub('aci statement', $this->aci, 'sql', []);
        $holder = '';
        $this->expectException(InvalidArgumentException::class);
        $stmt->bindColumn('holder', $holder, \PDO::PARAM_STR);
    }

    public function testBindColumnWithColumnNumberLessThanOne()
    {
        $stmt = new \TestACIStatementStub('aci statement', $this->aci, 'sql', []);
        $holder = '';
        $this->expectException(InvalidArgumentException::class);
        $stmt->bindColumn(0, $holder, \PDO::PARAM_STR);
    }

    public function testBindColumnWithInvalidDataType()
    {
        $stmt = new \TestACIStatementStub('aci statement', $this->aci, 'sql', []);
        $holder = '';
        $this->expectException(InvalidArgumentException::class);
        $stmt->bindColumn(1, $holder, 'hello');
    }

    public function testBindColumnSuccess()
    {
        $stmt = new \TestACIStatementStub('aci statement', $this->aci, 'sql', []);
        $holder = '';
        $this->assertTrue($stmt->bindColumn(1, $holder, \PDO::PARAM_STR, 40));

        $reflection = new \ReflectionClass($stmt);

        // bindings property
        $property = $reflection->getProperty('bindings');
        $property->setAccessible(true);
        $this->assertEquals([1 => ['var' => $holder, 'data_type' => \PDO::PARAM_STR, 'max_length' => 40, 'driverdata' => null]], $property->getValue($stmt));
    }

    public function testBindParamWithValidDataType()
    {
        global $ACIBindChangeStatus;
        $ACIBindChangeStatus = true;
        $variable = '';

        $stmt = new \TestACIStatementStub(true, new \TestACIStub(), '', []);
        $this->assertTrue($stmt->bindParam('param', $variable));
        $this->assertEquals('aci_bind_by_name', $variable);
    }

    public function testBindParamWithInvalidDataType()
    {
        $variable = '';
        $this->expectException(InvalidArgumentException::class);

        $stmt = new \TestACIStatementStub(true, new \TestACIStub(), '', []);
        $stmt->bindParam('param', $variable, 'hello');
    }

    public function testBindParamWithReturnDataType()
    {
        global $ACIBindChangeStatus;
        $ACIBindChangeStatus = true;
        $variable = '';

        $stmt = new \TestACIStatementStub(true, new \TestACIStub(), '', []);
        $this->assertTrue($stmt->bindParam('param', $variable, \PDO::PARAM_INPUT_OUTPUT));
        $this->assertEquals('aci_bind_by_name', $variable);
    }

    public function testBindValueWithValidDataType()
    {
        $this->assertTrue($this->stmt->bindValue('param', 'hello'));
    }

    public function testBindValueWithNullDataType()
    {
        global $ACIBindByNameTypeReceived;
        $this->assertTrue($this->stmt->bindValue('param', null, \PDO::PARAM_NULL));
        $this->assertSame(\SQLT_CHR, $ACIBindByNameTypeReceived);
    }    

    public function testBindValueWithInvalidDataType()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->stmt->bindValue(0, 'hello', 8);
    }

    // todo update this test once this method has been implemented
    public function testCloseCursor()
    {
        $this->assertTrue($this->stmt->closeCursor());
    }

    public function testColumnCount()
    {
        $this->assertEquals(1, $this->stmt->columnCount());
    }

    public function testDebugDumpParams()
    {
        global $ACIBindChangeStatus;
        $ACIBindChangeStatus = false;

        $this->assertEquals(print_r(['sql' => '', 'params' => []], true), $this->stmt->debugDumpParams());
        $stmt = new \TestACIStatementStub(true, true, 'select * from table where id = :0 and name = :1', []);
        $var = 'Hello';

        $stmt->bindParam(0, $var, \PDO::PARAM_INPUT_OUTPUT);
        $stmt->bindValue(1, 'hi');
        $this->assertEquals(print_r(['sql' => 'select * from table where id = :0 and name = :1',
            'params' => [
                ['paramno' => 0,
                    'name' => ':0',
                    'value' => $var,
                    'is_param' => 1,
                    'param_type' => \PDO::PARAM_INPUT_OUTPUT,
                ],
                ['paramno' => 1,
                    'name' => ':1',
                    'value' => 'hi',
                    'is_param' => 1,
                    'param_type' => \PDO::PARAM_STR,
                ],
            ], ], true), $stmt->debugDumpParams()
        );
    }

    public function testErrorCode()
    {
        $ocistmt = new \TestACIStatementStub(true, '', '', []);
        $this->assertNull($ocistmt->errorCode());

        // use reflection to test values of protected properties
        $reflection = new \ReflectionClass($ocistmt);

        // setErrorInfo
        $method = $reflection->getMethod('setErrorInfo');
        $method->setAccessible(true);
        $method->invoke($ocistmt, '11111', '2222', 'Testing the errors');

        $this->assertEquals('11111', $ocistmt->errorCode());
    }

    public function testErrorInfo()
    {
        $ocistmt = new \TestACIStatementStub(true, '', '', []);
        $this->assertEquals([0 => '', 1 => null, 2 => null], $ocistmt->errorInfo());

        // use reflection to test values of protected properties
        $reflection = new \ReflectionClass($ocistmt);

        // setErrorInfo
        $method = $reflection->getMethod('setErrorInfo');
        $method->setAccessible(true);
        $method->invoke($ocistmt, '11111', '2222', 'Testing the errors');

        $this->assertEquals([0 => '11111', 1 => '2222', 2 => 'Testing the errors'], $ocistmt->errorInfo());
    }

    public function testExecutePassesWithParameters()
    {
        $this->assertTrue($this->stmt->execute([0 => 1]));
    }

    public function testExecutePassesWithoutParameters()
    {
        $this->assertTrue($this->stmt->execute());
    }

    public function testExecuteFailesWithParameters()
    {
        global $ACIExecuteStatus;
        $ACIExecuteStatus = false;
        $this->assertFalse($this->stmt->execute([0 => 1]));
        $this->assertEquals('07000', $this->stmt->errorCode());
    }

    public function testExecuteFailesWithoutParameters()
    {
        global $ACIExecuteStatus;
        $ACIExecuteStatus = false;
        $this->assertFalse($this->stmt->execute());
        $this->assertEquals('07000', $this->stmt->errorCode());
    }

    public function testFetchWithBindColumn()
    {
        $this->aci->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_LOWER);
        $stmt = new \TestACIStatementStub('aci statement', $this->aci, 'sql', []);
        $holder = 'dad';
        $this->assertTrue($stmt->bindColumn(1, $holder, \PDO::PARAM_STR, 40));

        $reflection = new \ReflectionClass($stmt);

        // bindings property
        $property = $reflection->getProperty('bindings');
        $property->setAccessible(true);
        $this->assertEquals([1 => ['var' => $holder, 'data_type' => \PDO::PARAM_STR, 'max_length' => 40, 'driverdata' => null]], $property->getValue($stmt));

        $obj = $stmt->fetch(\PDO::FETCH_CLASS);

        $this->assertEquals([1 => ['var' => $holder, 'data_type' => \PDO::PARAM_STR, 'max_length' => 40, 'driverdata' => null]], $property->getValue($stmt));

        $this->assertEquals($obj->fname, $holder);
    }

    public function testFetchSuccessReturnArray()
    {
        // return lower case
        $this->aci->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_LOWER);
        $this->assertEquals($this->resultLowerArray, $this->stmt->fetch(\PDO::FETCH_ASSOC));
        $this->assertEquals($this->resultBothLowerArray, $this->stmt->fetch(\PDO::FETCH_BOTH));

        // return upper cased keyed object
        $this->aci->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_UPPER);
        $this->assertEquals($this->resultUpperArray, $this->stmt->fetch(\PDO::FETCH_ASSOC));
        $this->assertEquals($this->resultBothUpperArray, $this->stmt->fetch(\PDO::FETCH_BOTH));

        // return natural keyed object, in Oscar that is upper case
        $this->aci->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_NATURAL);
        $this->assertEquals($this->resultUpperArray, $this->stmt->fetch(\PDO::FETCH_ASSOC));
        $this->assertEquals($this->resultBothUpperArray, $this->stmt->fetch(\PDO::FETCH_BOTH));

        $this->assertEquals($this->resultNumArray, $this->stmt->fetch(\PDO::FETCH_NUM));
    }

    public function testFetchSuccessReturnObject()
    {
        // return lower cased keyed object
        $this->aci->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_LOWER);
        $this->assertEquals($this->resultLowerObject, $this->stmt->fetch(\PDO::FETCH_CLASS));

        // return upper cased keyed object
        $this->aci->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_UPPER);
        $this->assertEquals($this->resultUpperObject, $this->stmt->fetch(\PDO::FETCH_CLASS));

        // return natural keyed object, in Oscar that is upper case
        $this->aci->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_NATURAL);
        $this->assertEquals($this->resultUpperObject, $this->stmt->fetch(\PDO::FETCH_CLASS));
    }

    public function testFetchFail()
    {
        global $ACIFetchStatus;
        $ACIFetchStatus = false;
        $this->assertFalse($this->stmt->fetch());
        $this->assertEquals('07000', $this->stmt->errorCode());
    }

    public function testFetchAllSuccessReturnArray()
    {
        // return lower case
        $this->aci->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_LOWER);
        $this->assertEquals($this->resultAllLowerArray, $this->stmt->fetchAll(\PDO::FETCH_ASSOC));

        // return upper cased keyed object
        $this->aci->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_UPPER);
        $this->assertEquals($this->resultAllUpperArray, $this->stmt->fetchAll(\PDO::FETCH_ASSOC));

        // return natural keyed object, in Oscar that is upper case
        $this->aci->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_NATURAL);
        $this->assertEquals($this->resultAllUpperArray, $this->stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function testFetchAllSuccessReturnObject()
    {
        // return lower cased keyed object
        $this->aci->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_LOWER);
        $this->assertEquals($this->resultAllLowerObject, $this->stmt->fetchAll(\PDO::FETCH_CLASS));

        // return upper cased keyed object
        $this->aci->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_UPPER);
        $this->assertEquals($this->resultAllUpperObject, $this->stmt->fetchAll(\PDO::FETCH_CLASS));

        // return natural keyed object, in Oscar that is upper case
        $this->aci->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_NATURAL);
        $this->assertEquals($this->resultAllUpperObject, $this->stmt->fetchAll(\PDO::FETCH_CLASS));
    }

    public function testFetchAllFail()
    {
        global $ACIFetchStatus;
        $ACIFetchStatus = false;
        $this->assertFalse($this->stmt->fetchAll());
        $this->assertEquals('07000', $this->stmt->errorCode());
    }

    public function testFetchAllFailWithInvalidFetchStyle()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->stmt->fetchAll(\PDO::FETCH_BOTH);
    }

    public function testFetchColumnWithColumnNumber()
    {
        $this->assertEquals($this->resultNumArray[1], $this->stmt->fetchColumn(1));
    }

    public function testFetchColumnWithColumnName()
    {
        $this->expectException(ACIException::class);
        $this->stmt->fetchColumn('ColumnName');
    }

    public function testFetchObject()
    {
        // return lower cased keyed object
        $this->aci->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_LOWER);
        $this->assertEquals($this->resultLowerObject, $this->stmt->fetchObject());

        // return upper cased keyed object
        $this->aci->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_UPPER);
        $this->assertEquals($this->resultUpperObject, $this->stmt->fetchObject());

        // return natural keyed object, in Oscar that is upper case
        $this->aci->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_NATURAL);
        $this->assertEquals($this->resultUpperObject, $this->stmt->fetchObject());
    }

    public function testGetAttributeForValidAttribute()
    {
        $this->assertEquals('attribute', $this->stmt->getAttribute('fake'));
    }

    public function testGetAttributeForInvalidAttribute()
    {
        $this->assertEquals(null, $this->stmt->getAttribute('invalid'));
    }

    public function testGetColumnMetaWithColumnNumber()
    {
        $expected = ['native_type' => 1, 'driver:decl_type' => 1,
            'name' => 1, 'len' => 1, 'precision' => 1, ];

        $result = $this->stmt->getColumnMeta(0);
        $this->assertEquals($expected, $result);
    }

    public function testGetColumnMetaWithColumnName()
    {
        $this->expectException(ACIException::class);
        $this->stmt->getColumnMeta('ColumnName');
    }

    public function testNextRowset()
    {
        $this->assertTrue($this->stmt->nextRowset());
    }

    public function testRowCount()
    {
        $this->assertEquals(1, $this->stmt->rowCount());
    }

    public function testSetAttribute()
    {
        $this->assertTrue($this->stmt->setAttribute('testing', 'setAttribute'));
        $this->assertEquals('setAttribute', $this->stmt->getAttribute('testing'));
    }

    public function testSetFetchMode()
    {
        $this->assertTrue($this->stmt->setFetchMode(\PDO::FETCH_CLASS));
    }

    public function testGetACIResource()
    {
        $this->assertEquals('aci statement', $this->stmt->getACIResource());
    }    
}