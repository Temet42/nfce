<?php

namespace App\Http\Controllers;


//CCURL && OOBJ
//http://teste.oobj.com.br/monitor


use App\NotaFiscal;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

class Slave
{
    //LINK
    public $url = 'http://teste.oobj.com.br/oobj-rest-service'; //'http://rest.oobj-dfe.com.br';
    //AUTENTICAÇÃO
    public $user = 'null';
    public $senha = 'null';
   
    public $empresa = '';
    //ProduÃ§Ã£o => prod | Testes => hom
    public $ambiente = 'hom';
    //NFe => 55 | NFCe => 65
    public $codModelo = 65;
    public $ano = 2017;
    public $serie;
    public $numero;
    public $id_ultima_nota;
    public $chaveDeAcesso;
    public $token;
    public $last_url = 'not_cook';
    public $tpEmis = 1;
    public $cNF;
    public $cDV; // NFe
    public $loja = null;

    //SESSION
    public function set_token()
    {
        $this->last_url = $uri = $this->url . "/session";
        $response = $this->http_request([
            [CURLOPT_USERPWD, $this->user . ":" . $this->senha],
            [CURLOPT_URL, $uri],
            [CURLOPT_RETURNTRANSFER, true],
            [CURLOPT_POST, true]
        ]);

        $this->token = str_split($response, 14)[1];
        $this->token .= str_split($response, 14)[2];
        $this->token .= str_split($response, 14)[3];
        return strpos($response, 'x-auth-token') !== false;
    }
    public function get_token()
    {
        return $this->token;
    } //get token>>schedule:20 min

    //GETTER AND SETTER
    public function get_ano()
    {
        return $this->ano;
    }
    public function set_ano($ano)
    {
        $this->ano = $ano;
    }
    public function get_serie()
    {
        return $this->serie;
    }
    public function set_serie($serie)
    {
        $this->serie = $serie;
    }
    public function get_numero()
    {
        return $this->numero;
    }
    public function set_numero($numero)
    {
        $this->numero = $numero;
    }
    public function get_chave_de_acesso()
    {
        return $this->chaveDeAcesso;
    }
    public function set_chave_de_acesso($chaveDeAcesso)
    {
        $this->chaveDeAcesso = $chaveDeAcesso;
    }


    //EMISSÃ‚O
    public function send_xml($id, $dados_cliente = 'not_send')
    {
        $response = '';

        $loja = \App\Caixa::find($id)->lojamodel()->first();
        $this->loja = $loja;
        $this->empresa = $loja->cnpj;
        $this->user = $loja->oobj_login;
        $this->senha = $loja->oobj_password;

        $uri = $this->url . '/api/empresas/' . $this->empresa . '/docs/' . $this->ambiente . '/' . $this->codModelo . '?layout=sefaz';
        if ($this->set_token()) {
            $response = $this->http_request([
                [CURLOPT_URL, $uri],
                [CURLOPT_RETURNTRANSFER, true],
                [CURLOPT_POST, true],
                [CURLOPT_HTTPHEADER, Array('Content-Type: application/xml; charset=utf-8', 'x-auth-token: ' . $this->token)],
                [CURLOPT_POSTFIELDS, $this->nfce_factory($id, $dados_cliente)], 
            ]);
        } else {
            return ['error' => true, 'response' => 'Há algum problema com um dos itens a seguir: CNPJ, Certificado digital, Token, Secret, CSC'];
        }

        $this->last_url = $uri;
        $nota = \App\NotaFiscal::find($this->id_ultima_nota);
        sleep(5);

        $temp = $this->get_dfe_serie();

        try {
            $nota->status = json_decode($temp, false)->status;
        } catch (\Exception $ex) {
            return ['error' => true, 'response' => $temp];
        }

        if ($nota->status == "Autorizada") {
            $data = json_decode($temp, false);
            $nota->chave_acesso = $data->chaveAcesso;
            $this->set_chave_de_acesso($data->chaveAcesso);
        }

        $nota->save();
        $this->last_url = $uri;
        return ['error' => false, 'response' => $response];
    }
    public function get_dfe_serie()
    {
        $uri = $this->url . '/api/empresas/' . $this->empresa . '/docs/' . $this->ambiente . '/' . $this->codModelo . '/' . $this->ano . '/' . $this->serie . '/' . $this->numero;
        if ($this->set_token()) {
            $response = $this->http_request([
                [CURLOPT_URL, $uri],
                [CURLOPT_RETURNTRANSFER, true],
                [CURLOPT_HTTPHEADER, Array('Content-Type: application/xml; charset=utf-8', 'x-auth-token: ' . $this->token)]
            ]);
        } else {
            return ['error' => true, 'response' => 'Há algum problema com um dos itens a seguir: CNPJ, Certificado digital, Token, Secret, CSC'];
        }

        $this->last_url = $uri;
        return $response;
    }
    public function import_dfe($nf)
    {
        $uri = $this->url . '/api/empresas/' . $this->empresa . '/docs/' . $this->ambiente . '/' . $this->codModelo;
        if ($this->set_token()) {
            $response = $this->http_request([
                [CURLOPT_URL, $uri],
                [CURLOPT_RETURNTRANSFER, true],
                [CURLOPT_PUT, true],
                [CURLOPT_HTTPHEADER, Array('Content-Type: application/xml; charset=utf-8', 'x-auth-token: ' . $this->token)],
                [CURLOPT_POSTFIELDS, "xmlRequest=" . $this->get_nota($nf)]
            ]);
        } else {
            $response = 'fail';
        }
        $this->last_url = $uri;
        return $response;
    } //INCOMPLETO
    public function get_pdf()
    {
        $uri = $this->url . '/api/empresas/' . $this->loja->cnpj . '/docs/' . $this->ambiente . '/' . $this->codModelo . '/' . $this->serie . '.pdf';

        if ($this->set_token()) {
            $response = $this->http_request([
                [CURLOPT_URL, $uri],
                [CURLOPT_RETURNTRANSFER, true],
                [CURLOPT_HTTPHEADER, Array('x-auth-token: ' . $this->token)]
            ]);
        } else {
            $response = 'fail';
        }
        $this->last_url = $uri;
        return $response;
    }       //INCOMPLETO
    public function get_dfe_key()
    {
        $uri = $this->url . '/api/empresas/' . $this->empresa . '/docs/' . $this->ambiente . '/' . $this->codModelo . '/' . $this->chaveDeAcesso;
        if ($this->set_token()) {
            $response = $this->http_request([
                [CURLOPT_URL, $uri],
                [CURLOPT_RETURNTRANSFER, true],
                [CURLOPT_HTTPHEADER, Array('Content-Type: application/xml; charset=utf-8', 'x-auth-token: ' . $this->token)]
            ]);
        } else {
            $response = 'fail';
        }
        $this->last_url = $uri;
        return $response;
    }   //INCOMPLETO
    public function list_dfe()
    {
        $uri = $this->url . '/api/empresas/' . $this->empresa . '/docs/' . $this->ambiente . '/' . $this->codModelo;
        if ($this->set_token()) {
            $response = $this->http_request([
                [CURLOPT_URL, $uri],
                [CURLOPT_RETURNTRANSFER, true],
                [CURLOPT_HTTPHEADER, Array('Content-Type: application/xml; charset=utf-8', 'x-auth-token: ' . $this->token)]
            ]);
        } else {
            $response = 'fail';
        }
        $this->last_url = $uri;
        return $response;
    }      //INCOMPLETO

    //INNER
    public function __array(){
        return ['CPF' => '18211677083','NOME' => 'David Meth','EMAIL' => 'davidmeth@temet.com.br'];
    }
    public function get_stuff()
    {
        $this->set_token();
        return $this->token . " " . $this->url . '/api/empresas/' . $this->empresa . '/docs/' . $this->ambiente . '/' . $this->codModelo . '/' . $this->ano . '/' . $this->serie . '/' . $this->numero . '.pdf';
    }
    private function get_time()
    {
        $time = date("Y-m-d");
        $time .= 'T' . date("H:i:s") . '-03:00';
        return $time;
    }
    private function getinfNFe($caixa)
    {
        //UF_AAMM_CNPJ_MODELO_SERIE_NUMERONFE_FORMA-EMISSÃO_CODIGO-NUMERICO_DIGITO-VERIFICADOR


        $this->cNF = str_pad(NotaFiscal::get_num($caixa->loja), 8, "0", STR_PAD_LEFT);
        $this->cDV = substr($this->cNF, -1);

        return "NFe"
            . $caixa->lojamodel()->first()->uf
            . date('ym')
            . $caixa->lojamodel()->first()->cnpj
            . $this->codModelo
            . $this->serie
            . str_pad(NotaFiscal::get_num($caixa->loja), 9, "0", STR_PAD_LEFT)
            . $this->tpEmis
            . $this->cNF
            . $this->cDV;
    }
    private function nfce_factory($id, $dados_cliente)
    {
        // QUERY SELECT NS_INTRA
        /*

         ;;;;;select * from notas_fiscais where notas_fiscais.id = (select max(id) from notas_fiscais);select lojas.codigo as 'Loja ID',lojas.cnpj as 'CNPJ',caixa.codigo as 'Caixa ID', caixa.pagamento as 'Pagamento',formas_pagamento.descricao as 'Forma de Pagamento',estoque.codigo as 'Produto ID',estoque.descricao as 'Exemplo Produto',estoque.custo as 'Custo',estoque.valor as 'Valor',caixadetalhes.qtd as 'Quantidade', notas_fiscais.id as 'Numero',notas_fiscais.serie as 'Serie',notas_fiscais.v_pag as 'Total',notas_fiscais.status as 'Status' from lojas join caixa on lojas.codigo = caixa.loja join caixadetalhes on caixa.codigo = caixadetalhes.codcaixa join estoque on caixadetalhes.produto = estoque.codigo join notas_fiscais on notas_fiscais.caixa_cod = caixa.codigo join formas_pagamento on caixa.pagamento = formas_pagamento.codigo where notas_fiscais.id = (select max(id) from notas_fiscais);
        */
        $total['vProd'] = 0;
        $total['vDesc'] = 0;

        $nota = new \App\NotaFiscal;
        $nota->dt_hr_emissao = $this->get_time();
        $nota->caixa_cod = \App\Caixa::findOrFail($id)->codigo;

        // CAIXA.PAGAMENTO => INDPAG,TPAG
        /*
            +------------------+--------+
            | descricao        | codigo |
            +------------------+--------+
            | Dinheiro         |      1 |
            | DÃ©bito Visa      |      2 |
            | DÃ©bito Master    |      3 |
            | CrÃ©dito Visa     |      4 |
            | CrÃ©dito Master   |      5 |
            | CrÃ©dito Hiper    |      6 |
            | CrÃ©dito Amex     |      7 |
            | CrÃ©dito Ello     |      8 |
            | Fatura           |      9 |
            | CondiÃ§Ã£o Variada |     10 |
            | Pagseguro        |     11 |
            | Cielo e-Commerce |     12 |
            | BCash            |     13 |
            | DÃ©bito Elo       |     14 |
            +------------------+--------+

            +-----------+
            | pagamento |
            +-----------+
            | 5         |
            | 3         |
            | 4         |
            | 2         |
            | 1         |
            | 6         |
            | 9         |
            | 8         |
            | 7         |
            | 10        |
            | Dinheiro  |
            |           |
            | 11        |
            | 12        |
            | 13        |
            +-----------+

        indPag
						| 0,Pagamento Ã  vista
						| 1,Pagamento a prazo
						| 2,Outros
        tPag
						| 01,Dinheiro
						| 02,Cheque
						| 03,CartÃ£o de CrÃ©dito
						| 04,CartÃ£o de DÃ©bito
						| 05,CrÃ©dito Loja
						| 10,Vale AlimentaÃ§Ã£o
						| 11,Vale RefeiÃ§Ã£o
						| 12,Vale Presente
						| 13,Vale CombustÃ­vel
						| 99,Outros

        caixa.pagamento [1,Dinheiro]
			indPag 		| 0,Pagamento Ã  vista
			tPag		| 01,Dinheiro
        caixa.pagamento [2,3][14]
			indPag 		| 0,Pagamento Ã  vista
			tPag		| 04,CartÃ£o de DÃ©bito
        caixa.pagamento [4,8]
			indPag 		| 1,Pagamento a prazo
			tPag		| 03,CartÃ£o de CrÃ©dito
        caixa.pagamento [9]
			indPag 		| 1,Pagamento a prazo
			tPag		| 99,Outros
        caixa.pagamento [10]         !!!!!!!!
			indPag 		| 2,Outros
			tPag		| 99,Outros
        caixa.pagamento [11]
			indPag 		| 2,Outros
			tPag		| 99,Outros

         * */
        $flag_cartao = false;
        switch ($nota->caixa->pagamento) {
            default:
                $nota->ind_pag = '0';
                $nota->t_pag = '01';
                break;
            case 1:
                $nota->ind_pag = '0';
                $nota->t_pag = '01';
                break;
            case 2:
                $nota->ind_pag = '0';
                $nota->t_pag = '04';
                $flag_cartao = true;
                break;
            case 3:
                $nota->ind_pag = '0';
                $nota->t_pag = '04';
                $flag_cartao = true;
                break;
            case 4:
                $nota->ind_pag = '1';
                $nota->t_pag = '03';
                $flag_cartao = true;
                break;
            case 5:
                $nota->ind_pag = '1';
                $nota->t_pag = '03';
                $flag_cartao = true;
                break;
            case 6:
                $nota->ind_pag = '1';
                $nota->t_pag = '03';
                $flag_cartao = true;
                break;
            case 7:
                $nota->ind_pag = '1';
                $nota->t_pag = '03';
                $flag_cartao = true;
                break;
            case 8:
                $nota->ind_pag = '1';
                $nota->t_pag = '03';
                $flag_cartao = true;
                break;
            case 9:
                $nota->ind_pag = '1';
                $nota->t_pag = '99';
                break;
            case 10:
                $nota->ind_pag = '2';
                $nota->t_pag = '99';
                break;
            case 11:
                $nota->ind_pag = '2';
                $nota->t_pag = '99';
                break;
            case 14:
                $nota->ind_pag = '0';
                $nota->t_pag = '04';
                $flag_cartao = true;
                break;
            case 'Dinheiro':
                $nota->ind_pag = '0';
                $nota->t_pag = '01';
                break;
        }
        $xml_cartao = $flag_cartao ? "<card><tpIntegra>2</tpIntegra></card>" : "";

        $nota->save();
        $this->id_ultima_nota = $nota->id;
        $nota->serie = $nota->caixa->lojamodel()->first()->codigo;
        $this->serie = $nota->serie;
        $nota->numero = \App\NotaFiscal::get_num($nota->serie);
        $this->numero = $nota->numero;
        $xml = "<enviNFe xmlns=\"http://www.portalfiscal.inf.br/nfe\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" versao=\"3.10\">\n";
        $xml .= "<idLote>0002</idLote>\n";
        $xml .= "<indSinc>0</indSinc>\n";
        $xml .= "<NFe xmlns=\"http://www.portalfiscal.inf.br/nfe\">\n";
        $xml .= "<infNFe Id=\"" . $this->getinfNFe($nota->caixa) . "\" versao=\"3.10\">\n";
        $xml .= "<ide>\n";
        $xml .= "<cUF>" . $nota->caixa->lojamodel()->first()->uf . "</cUF>\n";

//        $xml .= "<cNF>00000089</cNF>\n";
        $xml .= "<cNF>" . $this->cNF . "</cNF>\n";

        $xml .= "<natOp>VENDA DE MERCADORIA</natOp>\n";
        $xml .= "<indPag>" . $nota->ind_pag . "</indPag>\n";
        $xml .= "<mod>" . $this->codModelo . "</mod>\n";
        $xml .= "<serie>" . $this->serie . "</serie>\n";
        $xml .= "<nNF>" . $this->numero . "</nNF>\n";
        $xml .= "<dhEmi>" . $nota->dt_hr_emissao . "</dhEmi>\n";
        $xml .= "<tpNF>1</tpNF>\n";
        $xml .= "<idDest>1</idDest>\n";
        $xml .= "<cMunFG>" . $nota->caixa->lojamodel()->first()->cidade . "</cMunFG>\n";
        $xml .= "<tpImp>4</tpImp>\n";
        $xml .= "<tpEmis>" . $this->tpEmis . "</tpEmis>\n";

        if ($this->tpEmis == '55')
            $xml .= "<cDV>" . $this->cDV . "</cDV>\n";

        if ($this->ambiente == 'hom')
            $xml .= "<tpAmb>2</tpAmb>\n";
        else
            $xml .= "<tpAmb>1</tpAmb>\n";

        $xml .= "<finNFe>1</finNFe>\n";
        $xml .= "<indFinal>1</indFinal>\n";
        $xml .= "<indPres>1</indPres>\n";
        $xml .= "<procEmi>3</procEmi>\n";
        $xml .= "<verProc>0</verProc>\n";
        $xml .= "</ide>\n";
        $xml .= "<emit>\n";
        $xml .= "<CNPJ>" . $nota->caixa->lojamodel()->first()->cnpj . "</CNPJ>\n";
        $xml .= "<xNome>" . $nota->caixa->lojamodel()->first()->razao . "</xNome>\n";
        $xml .= "<xFant>" . $nota->caixa->lojamodel()->first()->fantasia . "</xFant>\n";
        $xml .= "<enderEmit>\n";
        $xml .= "<xLgr>" . rtrim($nota->caixa->lojamodel()->first()->endereco) . "</xLgr>\n";
        $xml .= "<nro>" . $nota->caixa->lojamodel()->first()->numero . "</nro>\n";
        $xml .= "<xCpl>" . $nota->caixa->lojamodel()->first()->complemento . "</xCpl>\n";
        $xml .= "<xBairro>" . $nota->caixa->lojamodel()->first()->bairro . "</xBairro>\n";
        $xml .= "<cMun>" . $nota->caixa->lojamodel()->first()->cidade . "</cMun>\n"; //cod_mun tabela
        $xml .= "<xMun>" . \DB::table('cidades')->where('codigo', $nota->caixa->lojamodel()->first()->cidade)->first()->descricao . "</xMun>\n";
        $xml .= "<UF>" . \DB::table('uf')->where('codigo', $nota->caixa->lojamodel()->first()->uf)->first()->sigla . "</UF>\n";
        $xml .= "<CEP>" . $nota->caixa->lojamodel()->first()->cep . "</CEP>\n";
        $xml .= "<cPais>1058</cPais>\n";
        $xml .= "<xPais>BRASIL</xPais>\n";
        $xml .= "</enderEmit>\n";
        $xml .= "<IE>" . $nota->caixa->lojamodel()->first()->inscricao_estadual . "</IE>\n";
        $xml .= "<IM>" . $nota->caixa->lojamodel()->first()->inscricao_municipal . "</IM>\n";  //!!!!!!!!!
        $xml .= "<CNAE>" . $nota->caixa->lojamodel()->first()->cnae . "</CNAE>\n";             //!!!!!!!!!
        $xml .= "<CRT>" . $nota->caixa->lojamodel()->first()->crt . "</CRT>\n";
        $xml .= "</emit>\n";


        $dados_cliente = $nota->caixa->clientemodel()->first();
        if ($dados_cliente) {
            if (!empty($dados_cliente->email) && !empty($dados_cliente->cpf) && !empty($dados_cliente->nome)) {
                $xml .= "<dest>";
                $xml .= "<CPF>" . $dados_cliente->cpf . "</CPF>";
                $xml .= "<xNome>" . $dados_cliente->nome . "</xNome>";
                $xml .= "<indIEDest>9</indIEDest>";
                $xml .= "<email>" . $dados_cliente->email . "</email>";
                $xml .= "</dest>\n";
            }
        }

        $xml_cliente = $dados_cliente == 'not_send' ? '' : "<dest><CPF>" . $dados_cliente['CPF'] . "</CPF><xNome>" . $dados_cliente['NOME'] . "</xNome><indIEDest>9</indIEDest><email>" . $dados_cliente['EMAIL'] . "</email></dest>\n";


        $xml .= "<autXML>\n";
        //FULL POG ALERT!!!!!
        $xml .= "<CNPJ>" . $nota->caixa->lojamodel()->first()->cnpj . "</CNPJ>\n";                                   //!!!!!!!!!
        $xml .= "</autXML>\n";
        $cont = 1;

        $total['vProd'] = 0;
        foreach ($nota->caixa->itens as $produto) {

            if ($produto->ncm == '' || $produto->ncm == null) //TODO: SUPER POG
                $produto->ncm = '21069030';

            if ($produto->cfop == '' || $produto->cfop == null) //TODO: SUPER POG
                $produto->cfop = '5115';

            if ($produto->cst == '' || $produto->cst == null) //TODO: SUPER POG
                $produto->cst = '99';

            $prodpreco = $produto->pivot->valor;
            $total['vProd'] += $prodpreco * $produto->pivot->qtd;

            $xml .= "<det nItem=\"" . $cont++ . "\">\n";
            $xml .= "<prod>\n";
            $xml .= "<cProd>" . $produto->codigo . "</cProd>\n";
            $xml .= "<cEAN/>\n";
            $xml .= "<xProd>" . rtrim($produto->descricao) . "</xProd>\n";
            $xml .= "<NCM>" . $produto->ncm . "</NCM>\n";
            $xml .= "<CFOP>" . $produto->cfop . "</CFOP>\n";
            //FULL POG ALERT!!!!!
            $xml .= "<uCom>un</uCom>\n";
            $xml .= "<qCom>" . number_format($produto->pivot->qtd, 4, '.', '') . "</qCom>\n";
            //POG 27-05
            $xml .= "<vUnCom>" . number_format($prodpreco, 10, '.', '') . "</vUnCom>\n";

            $xml .= "<vProd>" . number_format(($prodpreco * $produto->pivot->qtd), 2, '.', '') . "</vProd>\n";
            $xml .= "<cEANTrib/>\n";
            //FULL POG ALERT!!!!!
            $xml .= "<uTrib>UN</uTrib>\n";
            $xml .= "<qTrib>" . number_format($produto->pivot->qtd, 4, '.', '') . "</qTrib>\n";
            $xml .= "<vUnTrib>" . number_format($prodpreco, 10, '.', '') . "</vUnTrib>\n";
            $total['vDesc'] += $produto->pivot->desconto;
            //FULL POG ALERT!!!!!
            $xml .= "<vDesc>0.00</vDesc>\n";                                     //!!!!!!!!!
            $xml .= "<indTot>1</indTot>\n";
            $xml .= "</prod>\n";
            $xml .= "<imposto>\n";
            $xml .= "<ICMS>\n";

            if ($this->codModelo == '65') {
                $xml .= "<ICMSSN102>\n";    //TODO: FULL POG ALERT!!!!!
                $xml .= "<orig>3</orig>\n";
                $xml .= "<CSOSN>102</CSOSN>\n";
                $xml .= "</ICMSSN102>\n";
            } elseif ($this->codModelo == '55') {
                $xml .= "<ICMSSN101>";      //TODO: FULL POG ALERT!!!!!
                $xml .= "<orig>3</orig>";
                $xml .= "<CSOSN>101</CSOSN>";
                $xml .= "<pCredSN>0.00</pCredSN>";
                $xml .= "<vCredICMSSN>00.00</vCredICMSSN>";
                $xml .= "</ICMSSN101>";
            }

            $xml .= "</ICMS>\n";
            $xml .= "<PIS>\n";
            $xml .= "<PISOutr>\n";
            $xml .= "<CST>" . $produto->cst . "</CST>\n";
            $xml .= "<qBCProd>0.0000</qBCProd>\n";
            $xml .= "<vAliqProd>0.0000</vAliqProd>\n";
            $xml .= "<vPIS>0.00</vPIS>\n";
            $xml .= "</PISOutr>\n";
            $xml .= "</PIS>\n";
            $xml .= "<COFINS>\n";
            $xml .= "<COFINSOutr>\n";
            $xml .= "<CST>" . $produto->cst . "</CST>\n";
            $xml .= "<qBCProd>0.0000</qBCProd>\n";
            $xml .= "<vAliqProd>0.0000</vAliqProd>\n";
            $xml .= "<vCOFINS>0.00</vCOFINS>\n";
            $xml .= "</COFINSOutr>\n";
            $xml .= "</COFINS>\n";
            $xml .= "</imposto>\n";
            $xml .= "</det>\n";
        }
        $xml .= "<total>\n";
        $xml .= "<ICMSTot>\n";
        $xml .= "<vBC>0.00</vBC>\n";
        $xml .= "<vICMS>0.00</vICMS>\n";
        $xml .= "<vICMSDeson>0.00</vICMSDeson>\n";
        $xml .= "<vFCPUFDest>0.00</vFCPUFDest>\n";
        $xml .= "<vICMSUFDest>0.00</vICMSUFDest>\n";
        $xml .= "<vICMSUFRemet>0.00</vICMSUFRemet>\n";
        $xml .= "<vBCST>0.00</vBCST>\n";
        $xml .= "<vST>0.00</vST>\n";

        $xml .= "<vProd>" . number_format($total['vProd'], 2, '.', '') . "</vProd>\n";
        $xml .= "<vFrete>0.00</vFrete>\n";
        $xml .= "<vSeg>0.00</vSeg>\n";
        //FULL POG ALERT!!!!!
        $xml .= "<vDesc>0.00</vDesc>\n";                                //!!!!!!!!!
        $xml .= "<vII>0.00</vII>\n";
        $xml .= "<vIPI>0.00</vIPI>\n";
        $xml .= "<vPIS>0.00</vPIS>\n";
        $xml .= "<vCOFINS>0.00</vCOFINS>\n";
        $xml .= "<vOutro>0.00</vOutro>\n";

        $xml .= "<vNF>" . number_format($nota->caixa->total, 2, '.', '') . "</vNF>\n";
        $xml .= "<vTotTrib>0.00</vTotTrib>\n";
        $xml .= "</ICMSTot>\n";
        $xml .= "</total>\n";
        $xml .= "<transp>\n";
        $xml .= "<modFrete>9</modFrete>\n";
        $xml .= "</transp>\n";
        $xml .= "<pag>\n";
        $xml .= "<tPag>" . $nota->t_pag . "</tPag>\n";
        //FULL POG ALERT!!!!!
        $xml .= "<vPag>" . number_format($nota->caixa->total, 2, '.', '') . "</vPag>\n";     //!!!!!!!!!
        $xml .= $xml_cartao;
        $xml .= "</pag>\n";
        $xml .= "<infAdic>\n";
        $xml .= "<infAdFisco>EMPRESA OPTANTE PELO REGIME SIMPLES NACIONAL, LEI COMPLEMENTAR 123/2006.DEVOLUCAO PARCIAL REFERENTE A NOTA FISCAL DE N 14936. CREDITO DO IMPOSTO PARA O DESTINATARIO. VALOR DO ICMS: R$ 24,43.</infAdFisco>\n";
        $xml .= "<infCpl>EMPRESA OPTANTE PELO REGIME SIMPLES NACIONAL, LEI COMPLEMENTAR 123/2006.DEVOLUCAO PARCIAL REFERENTE A NOTA FISCAL DE N 14936. CREDITO DO IMPOSTO PARA O DESTINATARIO. VALOR DO ICMS: R$ 24,43.</infCpl>\n";
        $xml .= "</infAdic>\n";
        $xml .= "</infNFe>\n";
        $xml .= "</NFe>\n";
        $xml .= "</enviNFe>\n";

//        echo $xml;
//        die('');

        $nota->v_pag = $nota->caixa->total;
        $nota->save();
        return $xml;
    }
    public function downloadPDF($url)
    {
//        if ($this->set_token()) {
//            $response = $this->http_request([
//                [CURLOPT_URL, $this->url . $url],
//                [CURLOPT_RETURNTRANSFER, true],
//                [CURLOPT_HTTPHEADER, Array('x-auth-token: ' . $this->token)]
//            ]);
//        } else {
//            $response = 'fail';
//        }
//
//        dd($response);
//
//        $chaveAcesso = json_decode($response)->chaveAcesso;
//        $this->set_chave_de_acesso($chaveAcesso);

        $response = $this->http_request([
            [CURLOPT_URL, $uri = $this->url . '/api/empresas/' . $this->loja->cnpj . '/docs/' . $this->ambiente . '/' . $this->codModelo . '/' . $this->chaveDeAcesso . '.pdf'],
            [CURLOPT_RETURNTRANSFER, true],
            [CURLOPT_HTTPHEADER, Array('x-auth-token: ' . $this->token)]
        ]);

        return base64_encode($response);
        //return Storage::disk('local')->put($chaveAcesso'.pdf', $response);

    }
    private function get_nota($nf)
    {
        $nota = "";
        $file = dirname(__FILE__) . "\\" . "files" . "\\" . $nf;
        $ponteiro = fopen($file, "r");
        while (!feof($ponteiro)) {
            $nota .= fgets($ponteiro, 4096);

        }
        fclose($ponteiro);
        return $nota;
    }
    private function http_request($options)
    {
        $ch = curl_init();
        foreach ($options as $option) {
            curl_setopt($ch, $option[0], $option[1]);
        }
        return curl_exec($ch);
    }
    public function go_jhonny()
    {
        for ($cont = 563; $cont <= 2157; $cont++) {
            echo '|' . $cont . ' - ' . $this->send_xml($cont) . ' - ' . (2157 - ($cont - 563));

        }
    }         //REVER
    private function get_url($function)
    {
        $uri = $this->url . '/api/empresas/' . $this->empresa;
        if ($function == 'unknow') {
            return $uri;
        }
        $uri .= '/docs/' . $this->ambiente . '/' . $this->codModelo;
        $uri .= ($function == 'serie') ? '/' . $this->ano . '/' . $this->serie . '/' . $this->numero
            : '/' . $this->chaveDeAcesso;
    } //REFATORAR


}

