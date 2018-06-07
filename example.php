<?php

include_once "src/mysql.php";

\LabCake\MySQL::setOption("hostname", "localhost");
\LabCake\MySQL::setOption("username", "root");
\LabCake\MySQL::setOption("password", "");
\LabCake\MySQL::setOption("database", "mytable");

$obj = new stdClass();
$obj->hello = "world";

$db = new \LabCake\MySQL("test");
$db['column1'] = "hello world";
$db->column2 = "hello world";
$db->column3 = array("this is an array"); // this will be json encoded
$db->column4 = $obj; // this will be serialized
$db->insert();

$db = new \LabCake\MySQL("test");
$db->setLimit(5);
$db->setColumns(array(
    "column1",
    "column2",
    "column3",
    "column4"
));
$db->select();
echo $db->getNumRows(); // get number of rows in query
$db->record(); // get next pending record from query results
while ($db->record()) { // loop all record rows from query results
    echo sprintf("%s, %s", $db->column1, $db['column3'])."<br />";
}

$db = new \LabCake\MySQL("test");
echo $db->count("column1 = '%s'", "hello world"). "<br />";

$db = new \LabCake\MySQL("test");
$db->delete("id = %s", 1);

$db = new \LabCake\MySQL("table1 as t1");
$db->setColumn("t2.column1");
$db->setLimit(1);
$db->join("left",  "table2 as t2","t1.id", "t2.id");
$db->select("t2.column1 = '%s'", "hello world");
$db->record();

echo $db->column1;

