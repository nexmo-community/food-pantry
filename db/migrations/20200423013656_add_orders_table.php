<?php

use Phinx\Migration\AbstractMigration;

class AddOrdersTable extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html
     *
     * The following commands can be used in this method and Phinx will
     * automatically reverse them when rolling back:
     *
     *    createTable
     *    renameTable
     *    addColumn
     *    addCustomColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Any other destructive changes will result in an error when trying to
     * rollback the migration.
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change()
    {
        $table = $this->table('orders');
        $table
            ->addColumn('users_uuid', 'string')
            ->addColumn('courier_phone', 'integer', ['default' => null, 'null' => true])
            ->addColumn('status', 'integer', ['default' => 0])
            ->addColumn('delivery_pin', 'integer', ['null' => true])
            ->addTimestamps()
        ;

        $table->save();
    }
}
