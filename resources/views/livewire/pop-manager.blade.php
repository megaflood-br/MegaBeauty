@foreach($pops as $pop)
    <button wire:click="downloadPop({{ $pop->id }})">
        Baixar {{ $pop->title }}
    </button>
@endforeach
