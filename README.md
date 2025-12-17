this command will generate model and migration
</br>
`php artisan make:model Name -m`
</br>
-m will create migration with the model
<hr>

to declare relation in laravel you have three types
<br>
this type for declaring who id is belongs to
for example Cart belong to one user 
```php 
 public function user()
    {
        return $this->belongsTo(User::class);
    }
```
HasOne Relation for example User has One Author 
```php 
public function author()
    {
        return $this->hasOne(Author::class);
    }
    ```
    
```
php artisan make:migration create_book_author_table
```
this command for declaring many to many table only will create migration
