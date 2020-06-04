{!! Form::open(array('route' => 'route.name', 'method' => 'POST')) !!}
	<ul>
		<li>
			{!! Form::label('id_transaction', 'Id_transaction:') !!}
			{!! Form::text('id_transaction') !!}
		</li>
		<li>
			{!! Form::label('id_versement', 'Id_versement:') !!}
			{!! Form::text('id_versement') !!}
		</li>
		<li>
			{!! Form::submit() !!}
		</li>
	</ul>
{!! Form::close() !!}