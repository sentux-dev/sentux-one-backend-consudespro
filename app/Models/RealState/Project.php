<?php
namespace App\Models\RealState;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Project extends Model
{
    use HasFactory, HasSlug;
    protected $table = 'real_state_projects';
    protected $fillable = ['name', 'slug', 'square_footage', 'infrastructure_cost', 'development_cost', 'lot_quantity', 'status'];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()->generateSlugsFrom('name')->saveSlugsTo('slug');
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function houseModels(): HasMany
    {
        return $this->hasMany(HouseModel::class, 'project_id');
    }

    public function extras(): HasMany
    {
        return $this->hasMany(Extra::class, 'project_id');
    }
    
    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class, 'project_id');
    }
}