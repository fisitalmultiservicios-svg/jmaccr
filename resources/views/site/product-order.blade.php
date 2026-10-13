<div class="product-order" data-product-order>
    <label class="product-quantity-control">Cantidad
        <input @if($quantityId??null) id="{{ $quantityId }}" @endif type="number" inputmode="numeric" min="1" max="{{ \App\Domain\Catalog\Catalog::quantityLimit($site['catalog']??[]) }}" step="1" value="1" required data-product-quantity aria-label="Cantidad de {{ $product->name }}">
    </label>
    <button type="button" data-cart-add data-id="{{ $product->id }}" data-name="{{ $product->name }}" data-sku="{{ $product->sku }}"><x-cart-icon /><span data-cart-label>Cargar al carrito</span></button>
</div>
