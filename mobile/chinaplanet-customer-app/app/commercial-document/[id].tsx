import { Redirect, useLocalSearchParams } from 'expo-router';

export default function LegacyCommercialDocumentRedirect() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const documentId = Array.isArray(id) ? id[0] : id;
  return <Redirect href={{ pathname: '/more/commercial-document/[id]', params: { id: documentId } }} />;
}
