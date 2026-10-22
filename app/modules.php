<?php
// Declarative master-data forms. Transaction modules use dedicated service methods.
return [
 'customers'=>['title'=>'Customers','table'=>'customers','fields'=>['name'=>['Full name','text',true],'email'=>['Email','email',true],'phone'=>['Contact number','text',true]]],
 'venues'=>['title'=>'Venues & tables','table'=>'venues','fields'=>['name'=>['Resource name','text',true],'capacity'=>['Guest capacity','number',true],'description'=>['Description','textarea',false],'active'=>['Availability','active',true]]],
 'packages'=>['title'=>'Event packages','table'=>'packages','fields'=>['name'=>['Package name','text',true],'price'=>['Price (PHP)','money',true],'inclusions'=>['Package inclusions','textarea',true],'active'=>['Availability','active',true]]],
 'products'=>['title'=>'Products & supplies','table'=>'products','fields'=>['name'=>['Item name','text',true],'sku'=>['SKU / item code','text',true],'category'=>['Category','text',true],'unit'=>['Unit (bottle, piece, etc.)','text',true],'price'=>['Selling price (PHP)','money',true],'minimum'=>['Minimum stock','number',true],'active'=>['Availability','active',true]]],
 'suppliers'=>['title'=>'Suppliers','table'=>'suppliers','fields'=>['name'=>['Business name','text',true],'contact'=>['Contact person','text',false],'email'=>['Email','email',false],'phone'=>['Phone','text',false],'active'=>['Availability','active',true]]],
 'employees'=>['title'=>'Employees','table'=>'employees','fields'=>['name'=>['Full name','text',true],'position'=>['Position','text',true],'phone'=>['Phone','text',false],'active'=>['Employment status','active',true]]],
];
