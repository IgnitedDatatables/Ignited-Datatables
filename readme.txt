### Ignited Datatables CodeIgniter 4
This is was adjusting version for CodeIgniter 4

Changelog:
- Change "from" method name to "table"

This adjustment is make change how to write the code
For this version, the table method should be called first, like so:



    $dt = new Datatables();
    $dt->table('table_name')->select('id, name');
    return $dt->generate();
