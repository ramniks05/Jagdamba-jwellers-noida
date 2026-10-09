<td class="text-end text-nowrap">
    @can('update', $record)
        <a href="{{ $editUrl }}">Edit</a>
    @endcan
    @can('delete', $record)
        <form class="d-inline" method="POST" action="{{ $deleteUrl }}" onsubmit="return confirm('Remove {{ addslashes($record->name) }}?')">
            @csrf
            @method('DELETE')
            <button class="btn btn-link text-danger p-0 ms-2 align-baseline" type="submit">Remove</button>
        </form>
    @endcan
</td>
