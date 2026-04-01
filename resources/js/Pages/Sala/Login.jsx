import { useForm } from '@inertiajs/react';
import { Head } from '@inertiajs/react';

export default function Login({ reunion }) {
    const { data, setData, post, processing, errors } = useForm({
        numero_documento: '',
        pin: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(`/sala/login/${reunion.id}`);
    };

    return (
        <>
            <Head title={`Acceso — ${reunion.titulo}`} />
            <div className="min-h-screen flex items-center justify-center bg-gray-50">
                <div className="max-w-md w-full space-y-8 p-8 bg-white rounded-xl shadow">
                    <div>
                        <p className="text-sm font-medium text-blue-600 uppercase tracking-wide">
                            {reunion.tenant.nombre}
                        </p>
                        <h2 className="text-2xl font-bold text-gray-900 mt-1">{reunion.titulo}</h2>
                        {reunion.fecha_programada && (
                            <p className="text-sm text-gray-400 mt-1">{reunion.fecha_programada}</p>
                        )}
                    </div>

                    <form onSubmit={submit} className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700">
                                Número de documento
                            </label>
                            <input
                                type="text"
                                value={data.numero_documento}
                                onChange={e => setData('numero_documento', e.target.value)}
                                className="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                autoFocus
                                autoComplete="off"
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700">
                                PIN de acceso
                            </label>
                            <input
                                type="text"
                                inputMode="numeric"
                                maxLength={6}
                                value={data.pin}
                                onChange={e => setData('pin', e.target.value.replace(/\D/g, ''))}
                                className="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 tracking-widest text-center text-xl focus:outline-none focus:ring-2 focus:ring-blue-500"
                                autoComplete="off"
                            />
                            {errors.pin && (
                                <p className="text-red-500 text-sm mt-1">{errors.pin}</p>
                            )}
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full bg-blue-600 text-white py-2 rounded-md hover:bg-blue-700 disabled:opacity-50 font-medium transition-colors"
                        >
                            {processing ? 'Verificando...' : 'Ingresar a la reunión'}
                        </button>
                    </form>
                </div>
            </div>
        </>
    );
}
