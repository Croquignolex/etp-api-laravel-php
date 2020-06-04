{!! Form::open(array('route' => 'route.name', 'method' => 'POST')) !!}
	<ul>
		<li>
			{!! Form::label('id_transaction', 'Id_transaction:') !!}
			{!! Form::text('id_transaction') !!}
		</li>
		<li>
			{!! Form::label('montant', 'Montant:') !!}
			{!! Form::text('montant') !!}
		</li>
		<li>
			{!! Form::submit() !!}
		</li>
	</ul>
{!! Form::close() !!}