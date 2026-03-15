<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Low Stock Notification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-bottom: 3px solid #e9ecef;
        }
        .content {
            padding: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .warning {
            color: #e67e22;
            font-weight: bold;
        }
        .critical {
            color: #e74c3c;
            font-weight: bold;
        }
        .status-icon {
            display: inline-block;
            vertical-align: middle;
            margin-right: 5px;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 15px;
            text-align: center;
            font-size: 12px;
            color: #6c757d;
            border-top: 1px solid #e9ecef;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Reorder Level Alert</h1>
            <p>Warehouse Inventory Management System - Jubaland</p>
        </div>
        
        <div class="content">
            <p>Hello,</p>
            
            <p>The following items in the warehouse inventory have reached or are below their <strong>reorder level</strong>.</p>
            
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Stock on Hand</th>
                        <th>Reorder Level</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $lowStockItems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                        <td><?php echo e($item->product->name); ?></td>
                        <td><?php echo e(number_format($item->quantity)); ?> <?php echo e($item->uom); ?></td>
                        <td><?php echo e(number_format($item->current_reorder_level ?? 0)); ?> <?php echo e($item->uom); ?></td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
            
            <p>Please review and take appropriate action to replenish these items.</p>
            
            <p>Thank you,<br>Warehouse Inventory Management System - Jubaland</p>
        </div>
        
        <div class="footer">
            <p>This is an automated message. Please do not reply to this email.</p>
            <p>&copy; <?php echo e(date('Y')); ?> Warehouse Management System</p>
        </div>
    </div>
</body>
</html>
<?php /**PATH /var/www/warehouse.mohjss.so/resources/views/emails/low-stock-notification.blade.php ENDPATH**/ ?>