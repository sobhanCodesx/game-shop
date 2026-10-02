<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DigitalProduct;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\DigitalProductMedia;
use App\Models\Game;
use App\Models\Platform;
use App\Models\User;
use App\Services\DigitalProductMediaStorage;
use App\Services\MediaOptimizationService;
use App\Services\MediaStorage;
use App\Support\RichText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DigitalProductController extends Controller
{
    public function __construct(private readonly MediaOptimizationService $mediaOptimizer) {}

    public function index(Request $request): Response
    {
        $query = DigitalProduct::query()->with(['category:id,name,slug','game:id,name,cover','platform:id,name','seller:id,name,email','offers','coverMedia'])->latest('id');
        $this->scopeSeller($query, $request->user());
        $products = $query->paginate(12)->withQueryString();
        $products->through(fn (DigitalProduct $product) => [
            'id'=>$product->id,'title'=>$product->title,'slug'=>$product->slug,'status'=>$product->status,'featured'=>(bool)$product->featured,'support_days'=>(int)$product->support_days,
            'category'=>$product->category?->only(['id','name','slug']),'game'=>$product->game?->only(['id','name']),'platform'=>$product->platform?->only(['id','name']),'seller'=>$product->seller?->only(['id','name']),
            'cover_url'=>DigitalProductMediaStorage::url($product->coverMedia?->path) ?: MediaStorage::url($product->game?->cover),
            'offers'=>$product->offers->map(fn ($offer)=>['id'=>$offer->id,'code'=>$offer->code,'label'=>$offer->label,'price'=>(int)$offer->price,'stock'=>(int)$offer->stock,'reserved_stock'=>(int)$offer->reserved_stock,'available_stock'=>$offer->availableStock(),'status'=>$offer->status])->values(),
        ]);
        return Inertia::render('Admin/Digital/Products/Index',['products'=>$products]);
    }

    public function create(Request $request): Response { return Inertia::render('Admin/Digital/Products/Form',$this->formData($request)); }

    public function store(Request $request): RedirectResponse
    {
        $data=$this->validateProduct($request); $actor=$request->user();
        $sellerId=$actor->role==='digital-seller'?$actor->id:(int)$data['seller_id'];
        $game=filled($data['game_id']??null)?Game::query()->findOrFail((int)$data['game_id']):null;
        $platform=Platform::query()->findOrFail($data['platform_id']);
        $title=trim((string)($data['title']??''))?:($game?"{$game->name} - {$platform->name}":'');
        if($title==='') throw ValidationException::withMessages(['title'=>'اگر بازی انتخاب نمی‌کنی، فقط عنوان محصول را وارد کن.']);
        $slugBase=Str::slug($title)?:'digital-game'; $slug=$slugBase;
        for($i=2;DigitalProduct::withTrashed()->where('slug',$slug)->exists();$i++) $slug=$slugBase.'-'.$i;
        DB::transaction(function()use($data,$sellerId,$title,$slug):void{
            $product=DigitalProduct::query()->create(['category_id'=>$data['category_id']??null,'game_id'=>$data['game_id']??null,'platform_id'=>$data['platform_id'],'seller_id'=>$sellerId,'title'=>$title,'slug'=>$slug,'short_description'=>RichText::sanitize($data['short_description']??null),'support_days'=>$data['support_days']??0,'status'=>$data['status']??'published','featured'=>(bool)($data['featured']??false)]);
            $this->syncOffers($product,$data['offers']??[]); $this->syncAttributeValues($product,$data['attribute_values']??[]); $this->syncMedia($product,$data['media']??[]);
        });
        return to_route('admin.digital-products.index')->with('success','محصول دیجیتال ایجاد شد.');
    }

    public function edit(Request $request, DigitalProduct $digitalProduct): Response
    {
        $this->authorizeProduct($request->user(),$digitalProduct); $digitalProduct->load(['game:id,name,slug,status','offers','media','attributeValues.attribute.options']);
        return Inertia::render('Admin/Digital/Products/Form',[...$this->formData($request),'product'=>$this->formProductPayload($digitalProduct)]);
    }

    public function update(Request $request, DigitalProduct $digitalProduct): RedirectResponse
    {
        $this->authorizeProduct($request->user(),$digitalProduct); $data=$this->validateProduct($request); $actor=$request->user();
        $game=filled($data['game_id']??null)?Game::query()->findOrFail((int)$data['game_id']):null; $platform=Platform::query()->findOrFail($data['platform_id']);
        $title=trim((string)($data['title']??''))?:($game?"{$game->name} - {$platform->name}":'');
        if($title==='') throw ValidationException::withMessages(['title'=>'اگر بازی انتخاب نمی‌کنی، فقط عنوان محصول را وارد کن.']);
        DB::transaction(function()use($data,$digitalProduct,$actor,$title):void{
            $digitalProduct->update(['category_id'=>$data['category_id']??null,'game_id'=>$data['game_id']??null,'platform_id'=>$data['platform_id'],'seller_id'=>$actor->role==='digital-seller'?$actor->id:(int)$data['seller_id'],'title'=>$title,'short_description'=>RichText::sanitize($data['short_description']??null),'support_days'=>$data['support_days']??0,'status'=>$data['status']??'published','featured'=>(bool)($data['featured']??false)]);
            $this->syncOffers($digitalProduct,$data['offers']??[]); $this->syncAttributeValues($digitalProduct,$data['attribute_values']??[]); $this->syncMedia($digitalProduct,$data['media']??[]);
        });
        return to_route('admin.digital-products.index')->with('success','محصول دیجیتال به‌روزرسانی شد.');
    }

    public function gameOptions(Request $request): JsonResponse
    {
        $search=trim($request->string('q')->toString()); $perPage=min(50,max(10,$request->integer('per_page',25)));
        $games=Game::query()->when($search!=='',fn($query)=>$query->where(function($q)use($search):void{$q->where('name','like','%'.$search.'%')->orWhere('slug','like','%'.$search.'%')->orWhere('developer','like','%'.$search.'%')->orWhere('publisher','like','%'.$search.'%');}))->orderBy('name')->paginate($perPage,['id','name','slug','status'])->withQueryString();
        return response()->json(['data'=>collect($games->items())->map(fn(Game $game)=>['id'=>$game->id,'name'=>$game->name,'slug'=>$game->slug,'status'=>$game->status])->values(),'meta'=>['current_page'=>$games->currentPage(),'last_page'=>$games->lastPage(),'per_page'=>$games->perPage(),'total'=>$games->total(),'has_more'=>$games->hasMorePages()]]);
    }

    private function validateProduct(Request $request): array
    {
        $sellerRule=$request->user()->role==='digital-seller'?['nullable']:['required','integer',Rule::exists('users','id')->where(fn($q)=>$q->where('role','digital-seller')->where('status','active'))];
        $data=$request->validate([
            'category_id'=>['nullable','integer',Rule::exists('categories','id')->whereNull('deleted_at')->where('status','active')],
            'game_id'=>['nullable','integer',Rule::exists('games','id')->whereNull('deleted_at')],
            // These two remain required because the database/business relation cannot safely persist without them.
            'platform_id'=>['required','integer',Rule::exists('platforms','id')->whereNull('deleted_at')],
            'seller_id'=>$sellerRule,
            'title'=>['nullable','string'],'short_description'=>['nullable','string'],'support_days'=>['nullable','integer','min:0','max:3650'],'status'=>['nullable',Rule::in(['draft','published','hidden'])],'featured'=>['nullable','boolean'],
            'offers'=>['nullable','array','max:4'],'offers.*.code'=>['nullable',Rule::in(['capacity_1','capacity_2','capacity_3','full']),'distinct'],'offers.*.label'=>['nullable','string','max:80'],'offers.*.price'=>['nullable','integer','min:0'],'offers.*.stock'=>['nullable','integer','min:0'],'offers.*.status'=>['nullable',Rule::in(['active','inactive'])],
            'attribute_values'=>['nullable','array'],'attribute_values.*'=>['nullable','array'],'attribute_values.*.*'=>['nullable','string'],
            'media'=>['nullable','array','max:12'],'media.*.id'=>['nullable','integer'],'media.*.type'=>['nullable',Rule::in(['image','video'])],'media.*.file'=>['nullable','file','mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime','max:2097152'],'media.*.alt'=>['nullable','string','max:255'],'media.*.is_primary'=>['nullable','boolean'],
        ]);
        $data['attribute_values']=$this->validateAttributeValues($data['attribute_values']??[]);
        // Empty media rows are harmless UI state; ignore them instead of rejecting the whole product.
        $data['media']=array_values(array_filter($data['media']??[],fn($media)=>!empty($media['id'])||!empty($media['file'])));
        foreach($data['media'] as $index=>$media){
            if(!empty($media['id'])&&!DigitalProductMedia::query()->whereKey($media['id'])->exists()) throw ValidationException::withMessages(["media.{$index}.id"=>'رسانه انتخاب‌شده معتبر نیست.']);
            if(!empty($media['file'])){$file=$media['file'];if($file->getMimeType()&&str_starts_with($file->getMimeType(),'image/')&&$file->getSize()>8*1024*1024) throw ValidationException::withMessages(["media.{$index}.file"=>'حجم تصویر نباید بیشتر از ۸ مگابایت باشد.']);}
        }
        return $data;
    }

    private function syncOffers(DigitalProduct $product,array $offers):void
    {
        foreach($offers as $index=>$offer){
            $code=$offer['code']??null; if(!in_array($code,['capacity_1','capacity_2','capacity_3','full'],true)) continue;
            $defaults=['capacity_1'=>'ظرفیت ۱','capacity_2'=>'ظرفیت ۲','capacity_3'=>'ظرفیت ۳','full'=>'فول ظرفیت'];
            $product->offers()->updateOrCreate(['code'=>$code],['label'=>trim((string)($offer['label']??''))?:$defaults[$code],'price'=>(int)($offer['price']??0),'stock'=>(int)($offer['stock']??0),'status'=>in_array($offer['status']??null,['active','inactive'],true)?$offer['status']:'inactive','sort_order'=>$index+1]);
        }
    }

    private function syncAttributeValues(DigitalProduct $product,array $values):void
    {
        $product->attributeValues()->delete(); foreach($values as $attributeId=>$items) foreach(array_values(array_filter((array)$items,fn($value)=>$value!==null&&$value!=='')) as $value) $product->attributeValues()->create(['attribute_id'=>(int)$attributeId,'value'=>(string)$value]);
    }

    private function validateAttributeValues(array $values):array
    {
        $attributes=$this->digitalAttributes()->keyBy('id'); $validated=[];
        foreach($attributes as $attribute){
            $items=array_values(array_unique(array_filter((array)($values[(string)$attribute->id]??$values[$attribute->id]??[]),fn($value)=>$value!==null&&$value!=='')));
            $allowed=$attribute->input_type==='boolean'?['1','0']:$attribute->options->where('status','active')->pluck('value')->map(fn($value)=>(string)$value)->all();
            // Ignore stale/unknown optional feature values; they are not dangerous to skip.
            $items=array_values(array_filter($items,fn($value)=>in_array((string)$value,$allowed,true)));
            if($attribute->input_type!=='multi_select'&&count($items)>1) $items=array_slice($items,0,1);
            if($items!==[]) $validated[(string)$attribute->id]=array_map('strval',$items);
        }
        return $validated;
    }

    private function digitalAttributes()
    {
        return Attribute::query()->with(['options'=>fn($query)=>$query->where('status','active')->orderBy('sort_order')])->where('status','active')->where('is_filterable',true)->whereIn('input_type',['select','multi_select','boolean'])->whereNotIn('slug',['capacity','platform'])->orderBy('sort_order')->orderBy('id')->get();
    }

    private function syncMedia(DigitalProduct $product,array $mediaItems):void
    {
        $existing=$product->media()->get()->keyBy('id'); $keptIds=[];
        foreach(array_values($mediaItems) as $index=>$item){
            $media=!empty($item['id'])?$existing->get((int)$item['id']):null;
            if(!empty($item['id'])&&!$media) throw ValidationException::withMessages(["media.{$index}.id"=>'این رسانه متعلق به محصول فعلی نیست.']);
            $file=$item['file']??null;
            if($file instanceof \Illuminate\Http\UploadedFile){$oldPath=$media?->path;$stored=$this->mediaOptimizer->store($file,'',DigitalProductMediaStorage::diskName());if($media)$media->update(['type'=>$stored['type'],'path'=>$stored['path']]);else $media=$product->media()->create(['type'=>$stored['type'],'path'=>$stored['path']]);if($oldPath&&$oldPath!==$stored['path'])DigitalProductMediaStorage::delete($oldPath);}
            if(!$media)continue;
            $media->update(['alt'=>trim((string)($item['alt']??''))?:null,'sort_order'=>$index+1,'is_primary'=>$media->type==='image'&&(bool)($item['is_primary']??false)]);$keptIds[]=$media->id;
        }
        $product->media()->whereNotIn('id',$keptIds?:[0])->get()->each(function(DigitalProductMedia $media):void{DigitalProductMediaStorage::delete($media->path);$media->delete();});
        $images=$product->media()->where('type','image')->orderBy('sort_order')->get(); if($images->isNotEmpty()&&!$images->contains(fn($media)=>$media->is_primary))$images->first()->update(['is_primary'=>true]);
        if($images->where('is_primary',true)->count()>1){$primary=$images->firstWhere('is_primary',true);$product->media()->where('type','image')->where('id','!=',$primary->id)->update(['is_primary'=>false]);}
    }

    private function formData(Request $request):array
    {
        $actor=$request->user();
        return ['product'=>null,'categories'=>Category::query()->where('status','active')->orderBy('sort_order')->orderBy('name')->get(['id','parent_id','name','slug']),'games'=>Game::query()->orderBy('name')->limit(25)->get(['id','name','slug','status']),'gameOptionsMeta'=>['current_page'=>1,'last_page'=>max(1,(int)ceil(Game::query()->count()/25)),'per_page'=>25,'total'=>Game::query()->count()],'platforms'=>Platform::query()->where('status','active')->orderBy('sort_order')->get(['id','name']),'sellers'=>$actor->role==='digital-seller'?collect([$actor->only(['id','name','email'])]):User::query()->where('status','active')->where('role','digital-seller')->orderBy('name')->get(['id','name','email']),'attributes'=>$this->digitalAttributes()->map(fn($attribute)=>['id'=>$attribute->id,'title'=>$attribute->title,'slug'=>$attribute->slug,'input_type'=>$attribute->input_type,'is_required'=>(bool)$attribute->is_required,'is_filterable'=>(bool)$attribute->is_filterable,'options'=>$attribute->input_type==='boolean'?[['title'=>'بله','value'=>'1'],['title'=>'خیر','value'=>'0']]:$attribute->options->map(fn($option)=>$option->only(['id','title','value']))->values()])->values(),'currentSellerId'=>$actor->role==='digital-seller'?$actor->id:null];
    }

    private function formProductPayload(DigitalProduct $product):array
    {
        return [...$product->only(['id','category_id','game_id','platform_id','seller_id','title','short_description','support_days','status','featured']),'game'=>$product->game?->only(['id','name','slug','status']),'offers'=>$product->offers->map(fn($offer)=>$offer->only(['id','code','label','price','stock','status']))->values(),'attribute_values'=>$product->attributeValues->groupBy('attribute_id')->map(fn($items)=>$items->pluck('value')->values())->all(),'media'=>$product->media->map(fn($media)=>[...$media->only(['id','type','alt','is_primary']),'url'=>DigitalProductMediaStorage::url($media->path)])->values()];
    }

    private function authorizeProduct(User $actor,DigitalProduct $product):void { if($actor->role==='digital-seller')abort_unless($product->seller_id===$actor->id,404); }
    private function scopeSeller($query,User $actor):void { if($actor->role==='digital-seller')$query->where('seller_id',$actor->id); }
}
