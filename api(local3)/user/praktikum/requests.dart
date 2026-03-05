// API wrapper: GET user/praktikum/requests.php, POST user/praktikum/request/create.php
// Path: mobile/api/user/praktikum/requests.dart
// Uses ApiService from lib. Envelope: { success, data, message? }

import '../../../lib/services/api_service.dart';
import '../../../lib/core/network/response_parser.dart';
import '../../../lib/core/network/api_exception.dart';
import '../../../lib/core/errors/feature_unavailable_exception.dart';
import '../../../lib/models/room_booking_master_data.dart';
import '../../../lib/features/praktikum_room_booking/domain/create_praktikum_room_request.dart';

const String _endpointGet = 'user/praktikum/requests.php';
const String _endpointCreate = 'user/praktikum/request/create.php';
const int _snippetMax = 300;

void _checkResponse(dynamic response, String endpoint) {
  final statusCode = response.statusCode ?? 0;
  final contentType = response.headers.value('content-type') ?? '';
  final ct = contentType.toLowerCase();
  // ignore: avoid_print
  print('[API][PRAKTIKUM] $endpoint status=$statusCode content-type=$contentType');
  if (statusCode == 404 || ct.contains('text/html')) {
    String? snippet;
    final data = response.data;
    if (data != null) {
      final s = data is String ? data : data.toString();
      snippet = s.length > _snippetMax ? s.substring(0, _snippetMax) : s;
    }
    throw FeatureUnavailableException(
      endpoint: endpoint,
      statusCode: statusCode,
      bodySnippet: snippet,
    );
  }
}

dynamic _parseEnvelope(Map<String, dynamic> map) {
  if (map['success'] != true) {
    final msg = map['message'] is String ? map['message'] as String : (map['message']?.toString() ?? 'Unknown error');
    throw ApiException(statusCode: 200, message: msg);
  }
  return map['data'];
}

class PraktikumRequestsApi {
  static Future<List<RequestJadwalPraktikumModel>> getRequests() async {
    final api = ApiService.instance;
    final response = await api.fetchGet(_endpointGet);
    _checkResponse(response, _endpointGet);

    final jsonMap = ResponseParser.parseJsonObject(response);
    final data = _parseEnvelope(jsonMap);
    if (data is! List) return [];

    final list = data as List;
    return list
        .where((e) => e is Map<String, dynamic>)
        .map((e) => RequestJadwalPraktikumModel.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList();
  }

  static Future<RequestJadwalPraktikumModel> createRequest(CreatePraktikumRoomRequest payload) async {
    final api = ApiService.instance;
    final response = await api.fetchPost(_endpointCreate, data: payload.toJson());
    _checkResponse(response, _endpointCreate);

    final jsonMap = ResponseParser.parseJsonObject(response);
    final data = _parseEnvelope(jsonMap);
    if (data is Map<String, dynamic>) {
      return RequestJadwalPraktikumModel.fromJson(data);
    }
    return RequestJadwalPraktikumModel(
      id: 0,
      userId: payload.userId,
      semesterId: payload.semesterId,
      tgl: DateTime.parse(payload.tgl),
      jurusan: payload.jurusan,
      penanggungJawab: payload.penanggungJawab,
      kelas: payload.kelas,
      tingkat: payload.tingkat,
      ruang: payload.ruang,
      hari: payload.hari,
      waktuMulai: payload.waktuMulai,
      waktuSelesai: payload.waktuSelesai,
      tujuan: payload.tujuan,
      status: payload.status ?? 'Menunggu',
      keterangan: payload.keterangan,
    );
  }
}
