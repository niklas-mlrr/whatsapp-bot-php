import axios from 'axios';
export interface WhatsAppMessage {
  id: number;
  sender: string;
  chat: string;
  type: string;
  content: string;
  sending_time: string;
  created_at: string;
}

export interface PaginatedResponse<T> {
  data: T[];
  links: any;
  meta: any;
}

export const fetchMessages = (params: Record<string, any>) =>
  axios.get<PaginatedResponse<WhatsAppMessage>>('/api/messages', { params });

export const fetchChats = () =>
  axios.get<{ data: string[] }>('/api/chats');

export const sendMessage = (data: {
  sender: string;
  chat: string;
  type: string;
  content?: string;
  media?: string;
  mimetype?: string;
  sending_time?: string;
}) =>
  axios.post('/api/messages', data);

export const uploadImage = (file: File) => {
  const formData = new FormData();
  formData.append('file', file);
  return axios.post<{ path: string; url: string }>('/api/upload', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
};