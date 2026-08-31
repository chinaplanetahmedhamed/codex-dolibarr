import { Redirect, useLocalSearchParams } from 'expo-router';

export default function LegacyServiceInvoiceRedirect() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const invoiceId = Array.isArray(id) ? id[0] : id;
  return <Redirect href={{ pathname: '/more/service-invoice/[id]', params: { id: invoiceId } }} />;
}
