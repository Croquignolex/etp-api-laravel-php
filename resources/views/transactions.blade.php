{!! Form::open(array('route' => 'route.name', 'method' => 'POST')) !!}
	<ul>
		<li>
			{!! Form::label('id_user', 'Id_user:') !!}
			{!! Form::text('id_user') !!}
		</li>
		<li>
			{!! Form::label('id_versement', 'Id_versement:') !!}
			{!! Form::text('id_versement') !!}
		</li>
		<li>
			{!! Form::label('id_type_transaction', 'Id_type_transaction:') !!}
			{!! Form::text('id_type_transaction') !!}
		</li>
		<li>
			{!! Form::label('id_flote', 'Id_flote:') !!}
			{!! Form::text('id_flote') !!}
		</li>
		<li>
			{!! Form::label('montant', 'Montant:') !!}
			{!! Form::text('montant') !!}
		</li>
		<li>
			{!! Form::label('reste', 'Reste:') !!}
			{!! Form::text('reste') !!}
		</li>
		<li>
			{!! Form::label('statut', 'Statut:') !!}
			{!! Form::text('statut') !!}
		</li>
		<li>
			{!! Form::label('user_destination', 'User_destination:') !!}
			{!! Form::text('user_destination') !!}
		</li>
		<li>
			{!! Form::label('user_source', 'User_source:') !!}
			{!! Form::text('user_source') !!}
		</li>
		<li>
			{!! Form::submit() !!}
		</li>
	</ul>
{!! Form::close() !!}